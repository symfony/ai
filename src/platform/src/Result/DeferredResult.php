<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Metadata\StreamListener as MetaDataStreamListener;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\TokenUsage\StreamListener as TokenUsageStreamListener;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DeferredResult
{
    use MetadataAwareTrait;
    use TypedResultTrait;

    private bool $isConverted = false;
    private ResultInterface $convertedResult;
    private ?\Throwable $conversionFailure = null;

    /**
     * Shared stream generator so the one-shot stream is driven exactly once
     * across asStream(), asStreamedObject() and asObject().
     */
    private ?\Generator $stream = null;

    /**
     * @var list<\Closure(ResultInterface): ResultInterface>
     */
    private array $onConvert = [];

    /**
     * @var list<\Closure(\Throwable): void>
     */
    private array $onError = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly ResultConverterInterface $resultConverter,
        private readonly RawResultInterface $rawResult,
        private readonly array $options = [],
    ) {
    }

    /**
     * Registers a callback invoked with the converted result once conversion succeeds.
     *
     * The callback may return a replacement result, which is then used as the converted result
     * and handed to any subsequently registered callback. Callbacks run in registration order.
     *
     * @param \Closure(ResultInterface): ResultInterface $callback
     */
    public function onConvert(\Closure $callback): void
    {
        $this->onConvert[] = $callback;
    }

    /**
     * Registers a callback invoked with the thrown exception when conversion fails.
     *
     * Callbacks run in registration order.
     *
     * @param \Closure(\Throwable): void $callback
     */
    public function onError(\Closure $callback): void
    {
        $this->onError[] = $callback;
    }

    /**
     * @throws ExceptionInterface
     */
    public function getResult(): ResultInterface
    {
        if (null !== $this->conversionFailure) {
            throw $this->conversionFailure;
        }

        if ($this->isConverted) {
            return $this->convertedResult;
        }

        try {
            $this->convertedResult = $this->resultConverter->convert($this->rawResult, $this->options);

            if (null === $this->convertedResult->getRawResult()) {
                // Fallback to set the raw result when it was not handled by the ResultConverter itself
                $this->convertedResult->setRawResult($this->rawResult);
            }

            if ($this->convertedResult instanceof StreamResult) {
                // Register listeners to promote stream metadata deltas to result metadata
                $this->convertedResult->addListener(new MetaDataStreamListener());
                $this->convertedResult->addListener(new TokenUsageStreamListener());
            }

            $metadata = $this->convertedResult->getMetadata();
            $metadata->merge($this->getMetadata());

            if (null !== $tokenUsageExtractor = $this->resultConverter->getTokenUsageExtractor()) {
                if (null !== $tokenUsage = $tokenUsageExtractor->extract($this->rawResult, $this->options)) {
                    $metadata->add('token_usage', $tokenUsage);
                }
            }

            $this->metadata->set($metadata->all());

            $this->isConverted = true;
        } catch (\Throwable $exception) {
            $this->conversionFailure = $exception;

            foreach ($this->onError as $callback) {
                $callback($exception);
            }

            throw $exception;
        }

        // Run conversion callbacks outside the try/catch: conversion has already
        // succeeded, so a throwing callback must not be reported as a conversion
        // failure nor leave the result in a half-converted state.
        foreach ($this->onConvert as $callback) {
            $this->convertedResult = $callback($this->convertedResult);
        }

        return $this->convertedResult;
    }

    public function getResultConverter(): ResultConverterInterface
    {
        return $this->resultConverter;
    }

    public function getRawResult(): RawResultInterface
    {
        return $this->rawResult;
    }

    /**
     * @throws ExceptionInterface
     */
    public function asObject(): object
    {
        $result = $this->getResult();

        if ($result instanceof StreamResult && null !== $listener = $this->findPartialObjectListener($result)) {
            $finalResult = $listener->getFinalObjectResult();

            if (null === $finalResult) {
                // Pump the remainder via next() instead of re-iterating: the
                // stream may be mid-flight (asStreamedObject() stopped early) and
                // cannot be restarted without reprocessing deltas. This finishes
                // it and fires the listener's completion handler.
                $generator = $this->stream ??= $result->getContent();

                try {
                    while ($generator->valid()) {
                        $generator->next();
                    }
                } finally {
                    $this->getMetadata()->set($result->getMetadata()->all());
                }

                $finalResult = $listener->getFinalObjectResult();
            }

            if (null === $finalResult) {
                throw new UnexpectedResultTypeException(ObjectResult::class, StreamResult::class);
            }

            return $finalResult->getContent();
        }

        return $this->as(ObjectResult::class)->getContent();
    }

    /**
     * @throws ExceptionInterface
     */
    public function asStream(): \Generator
    {
        $result = $this->as(StreamResult::class);
        $generator = $this->stream ??= $result->getContent();

        try {
            yield from $generator;
        } finally {
            $this->getMetadata()->set($result->getMetadata()->all());
        }
    }

    private function findPartialObjectListener(StreamResult $result): ?PartialObjectStreamListener
    {
        foreach ($result->getListeners() as $listener) {
            if ($listener instanceof PartialObjectStreamListener) {
                return $listener;
            }
        }

        return null;
    }
}
