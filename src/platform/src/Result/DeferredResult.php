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
use Symfony\AI\Platform\Result\Stream\CallbackStreamListener;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\StreamListener;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DeferredResult
{
    use MetadataAwareTrait;

    private bool $isConverted = false;
    private bool $isResolved = false;
    private ResultInterface $convertedResult;

    /**
     * @var list<\Closure(): void>
     */
    private array $onResolvedCallbacks = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly ResultConverterInterface $resultConverter,
        private readonly RawResultInterface $rawResult,
        private readonly array $options = [],
    ) {
    }

    public function __destruct()
    {
        try {
            $this->resolve();
        } catch (\Throwable) {
        }
    }

    /**
     * @throws ExceptionInterface|\Throwable
     */
    public function getResult(): ResultInterface
    {
        if (!$this->isConverted) {
            try {
                $this->convertedResult = $this->resultConverter->convert($this->rawResult, $this->options);

                if (null === $this->convertedResult->getRawResult()) {
                    // Fallback to set the raw result when it was not handled by the ResultConverter itself
                    $this->convertedResult->setRawResult($this->rawResult);
                }

                if ($this->convertedResult instanceof StreamResult) {
                    // Register listener to promote TokenUsage to metadata
                    $this->convertedResult->addListener(new StreamListener());

                    // Release resources when stream completes or is abandoned
                    $this->convertedResult->addListener(new CallbackStreamListener(function (): void {
                        $this->resolve();
                    }));
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
            } catch (\Throwable $throwable) {
                $this->resolve();

                throw $throwable;
            }
        }

        if (!$this->convertedResult instanceof StreamResult) {
            $this->resolve();
        }

        return $this->convertedResult;
    }

    /**
     * @param \Closure(): void $callback
     */
    public function onResolved(\Closure $callback): self
    {
        if ($this->isResolved) {
            $callback();

            return $this;
        }

        $this->onResolvedCallbacks[] = $callback;

        return $this;
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
        return $this->as(ObjectResult::class)->getContent();
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
    public function asStream(): \Generator
    {
        yield from $this->as(StreamResult::class)->getContent();
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

    /**
     * @param class-string $type
     *
     * @throws ExceptionInterface
     */
    private function as(string $type): ResultInterface
    {
        $result = $this->getResult();

        if (!$result instanceof $type) {
            throw new UnexpectedResultTypeException($type, $result::class);
        }

        return $result;
    }

    private function resolve(): void
    {
        if ($this->isResolved) {
            return;
        }

        $this->isResolved = true;

        foreach ($this->onResolvedCallbacks as $callback) {
            $callback();
        }

        $this->onResolvedCallbacks = [];
    }
}
