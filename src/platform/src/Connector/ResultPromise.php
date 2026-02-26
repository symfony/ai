<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Connector;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface as ConverterResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultPromise
{
    private \Closure $resultConverter;
    private bool $isConverted = false;
    private ConverterResult $convertedResult;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly ResultInterface $result,
        private readonly array $options = [],
    ) {
    }

    public function registerConverter(\Closure $resultConverter): void
    {
        if (isset($this->resultConverter)) {
            throw new RuntimeException('A result converter has already been registered for this promise.');
        }

        $this->resultConverter = $resultConverter;
    }

    public function getResult(): ConverterResult
    {
        return $this->await();
    }

    public function getRawResponse(): ResultInterface
    {
        return $this->result;
    }

    public function await(): ConverterResult
    {
        if (!$this->isConverted) {
            if (!isset($this->resultConverter)) {
                throw new RuntimeException('No result converter registered to handle the raw result.');
            }

            $this->convertedResult = ($this->resultConverter)($this->result, $this->options);

            if (null === $this->convertedResult->getRawResponse()) {
                // Fallback to set the raw response when it was not handled by the response converter itself
                $this->convertedResult->setRawResponse($this->result);
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
        $response = $this->as(BinaryResult::class);

        \assert($response instanceof BinaryResult);

        return $response->toDataUri();
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
    private function as(string $type): ConverterResult
    {
        $response = $this->getResult();

        if (!$response instanceof $type) {
            throw new UnexpectedResultTypeException($type, $response::class);
        }

        return $response;
    }
}
