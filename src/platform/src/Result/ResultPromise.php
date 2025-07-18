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

use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultPromise
{
    private bool $isConverted = false;
    private ResultInterface $convertedResult;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly \Closure $resultConverter,
        private readonly RawResultInterface $rawResult,
        private readonly array $options = [],
    ) {
    }

    public function getResult(): ResultInterface
    {
        return $this->await();
    }

    public function getRawResult(): RawResultInterface
    {
        return $this->rawResult;
    }

    public function await(): ResultInterface
    {
        if (!$this->isConverted) {
            $this->convertedResult = ($this->resultConverter)($this->rawResult, $this->options);

            if (null === $this->convertedResult->getRawResult()) {
                // Fallback to set the raw result when it was not handled by the ResultConverter itself
                $this->convertedResult->setRawResult($this->rawResult);
            }

            $this->isConverted = true;
        }

        return $this->convertedResult;
    }

    public function asText(): string
    {
        return $this->as(TextResult::class)->getContent();
    }

    public function asObject(): object
    {
        return $this->as(ObjectResult::class)->getContent();
    }

    public function asBinary(): string
    {
        return $this->as(BinaryResult::class)->getContent();
    }

    public function asBase64(): string
    {
        $result = $this->as(BinaryResult::class);

        \assert($result instanceof BinaryResult);

        return $result->toDataUri();
    }

    /**
     * @return Vector[]
     */
    public function asVectors(): array
    {
        return $this->as(VectorResult::class)->getContent();
    }

    public function asStream(): \Generator
    {
        yield from $this->as(StreamResult::class)->getContent();
    }

    /**
     * @return ToolCall[]
     */
    public function asToolCalls(): array
    {
        return $this->as(ToolCallResult::class)->getContent();
    }

    /**
     * @param class-string $type
     */
    private function as(string $type): ResultInterface
    {
        $result = $this->getResult();

        if (!$result instanceof $type) {
            throw new UnexpectedResultTypeException($type, $result::class);
        }

        return $result;
    }
}
