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
use Symfony\AI\Platform\ResultConverterInterface;
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

            $this->isConverted = true;
        }

        $this->convertedResult->getMetadata()->set($this->getMetadata()->all());

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
}
