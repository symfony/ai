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
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialJsonParser;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Typed accessors for a lazily produced result, narrowing it to the expected result type.
 *
 * A {@see MultiPartResult} containing exactly one part of the expected type is narrowed to that part.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
trait TypedResultTrait
{
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
     * @return non-empty-list<ResultInterface>
     *
     * @throws ExceptionInterface
     */
    public function asMultiPart(): array
    {
        return $this->as(MultiPartResult::class)->getContent();
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
     * @return ToolCall[]
     *
     * @throws ExceptionInterface
     */
    public function asToolCalls(): array
    {
        return $this->as(ToolCallResult::class)->getContent();
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
     * @throws ExceptionInterface
     */
    abstract public function getResult(): ResultInterface;

    /**
     * Yields the deltas of a streamed result.
     */
    abstract public function asStream(): \Generator;

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
