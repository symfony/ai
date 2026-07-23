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

use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchJobResult;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Metadata\StreamListener as MetaDataStreamListener;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialJsonParser;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\TokenUsage\StreamListener as TokenUsageStreamListener;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DeferredResult
{
    use MetadataAwareTrait;

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
    public function asText(): string
    {
        return $this->as(TextResult::class)->getContent();
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
     * Yields progressively populated instances of the target class as the
     * model emits more tokens. Each yielded value is the typed object itself;
     * consumers that also need the raw JSON buffer can iterate asStream() and
     * inspect the underlying PartialObjectDelta instances directly.
     *
     * @return \Generator<object>
     *
     * @throws ExceptionInterface
     */
    public function asStreamedObject(): \Generator
    {
        foreach ($this->asStream() as $delta) {
            if (!$delta instanceof PartialObjectDelta) {
                continue;
            }

            yield $delta->getObject();
        }
    }

    /**
     * @throws ExceptionInterface
     */
    public function asBinary(): string
    {
        return $this->as(BinaryResult::class)->getContent();
    }

    /**
     * @throws ExceptionInterface
     */
    public function asFile(string $path): void
    {
        $result = $this->as(BinaryResult::class);

        \assert($result instanceof BinaryResult);

        $result->asFile($path);
    }

    /**
     * @throws ExceptionInterface
     */
    public function asDataUri(?string $mimeType = null): string
    {
        $result = $this->as(BinaryResult::class);

        \assert($result instanceof BinaryResult);

        return $result->toDataUri($mimeType);
    }

    /**
     * @return Vector[]
     *
     * @throws ExceptionInterface
     */
    public function asVectors(): array
    {
        return $this->as(VectorResult::class)->getContent();
    }

    /**
     * @throws ExceptionInterface
     */
    public function asBatchJob(): BatchJob
    {
        $result = $this->as(BatchJobResult::class);
        \assert($result instanceof BatchJobResult);

        return $result->getContent();
    }

    /**
     * @return list<RerankingEntry>
     *
     * @throws ExceptionInterface
     */
    public function asReranking(): array
    {
        return $this->as(RerankingResult::class)->getContent();
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

    /**
     * @return \Generator<TextDelta>
     *
     * @throws ExceptionInterface
     */
    public function asTextStream(): \Generator
    {
        foreach ($this->asStream() as $delta) {
            if (!$delta instanceof TextDelta) {
                continue;
            }

            yield $delta;
        }
    }

    /**
     * Accumulates text deltas from the stream and yields the largest valid
     * structure recoverable from the buffer so far. A new snapshot is only
     * emitted when the parsed value differs from the previously yielded one,
     * which lets consumers render partial structured output progressively
     * without having to wire up the parser themselves.
     *
     * @return \Generator<mixed>
     *
     * @throws ExceptionInterface
     */
    public function asPartialJsonStream(): \Generator
    {
        $buffer = '';
        $hasPrevious = false;
        $previous = null;

        foreach ($this->asTextStream() as $delta) {
            $buffer .= $delta->getText();

            $partial = PartialJsonParser::parse($buffer, $error);

            if (null !== $error) {
                continue;
            }

            if ($hasPrevious && $partial === $previous) {
                continue;
            }

            $hasPrevious = true;
            $previous = $partial;

            yield $partial;
        }
    }

    /**
     * @return ToolCall[]
     *
     * @throws ExceptionInterface
     */
    public function asToolCalls(): array
    {
        return $this->as(ToolCallResult::class)->getContent();
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

    /**
     * @param class-string $type
     *
     * @throws ExceptionInterface
     */
    private function as(string $type): ResultInterface
    {
        $result = $this->getResult();

        if ($result instanceof MultiPartResult) {
            $parts = array_filter($result->getContent(), static fn (ResultInterface $part) => $part instanceof $type);
            if (1 === \count($parts)) {
                $result = array_first($parts);
            }
        }

        if (!$result instanceof $type) {
            throw new UnexpectedResultTypeException($type, $result::class);
        }

        return $result;
    }
}
