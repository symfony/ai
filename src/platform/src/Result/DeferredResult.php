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
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\ResultConverterInterface;
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
     * @throws ExceptionInterface
     */
    public function getResult(): ResultInterface
    {
        if (!$this->isConverted) {
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

            $this->getMetadata()->set($metadata->all());

            $this->isConverted = true;
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
        $content = $this->as(ObjectResult::class)->getContent();

        if (\is_array($content)) {
            $content = (object) $content;
        }

        return $content;
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

        try {
            yield from $result->getContent();
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
     * @return ToolCall[]
     *
     * @throws ExceptionInterface
     */
    public function asToolCalls(): array
    {
        return $this->as(ToolCallResult::class)->getContent();
    }

    /**
     * @template T of ResultInterface
     *
     * @param class-string<T> $type
     *
     * @return T
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
