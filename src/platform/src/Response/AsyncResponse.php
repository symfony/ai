<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Response;

use Symfony\AI\Platform\Response\Exception\RawResponseAlreadySetException;
use Symfony\AI\Platform\Response\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\ResponseConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AsyncResponse implements ResponseInterface
{
    use MetadataAwareTrait;

    private bool $isConverted = false;
    private ResponseInterface $convertedResponse;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly ResponseConverterInterface $responseConverter,
        private readonly HttpResponse $response,
        private readonly array $options = [],
    ) {
    }

    public function getContent(): string|iterable|object|null
    {
        return $this->unwrap()->getContent();
    }

    public function getRawResponse(): HttpResponse
    {
        return $this->response;
    }

    public function setRawResponse(HttpResponse $rawResponse): void
    {
        // Empty by design as the raw response is already set in the constructor and must only be set once
        throw new RawResponseAlreadySetException();
    }

    public function unwrap(): ResponseInterface
    {
        if (!$this->isConverted) {
            $this->convertedResponse = $this->responseConverter->convert($this->response, $this->options);

            if (null === $this->convertedResponse->getRawResponse()) {
                // Fallback to set the raw response when it was not handled by the response converter itself
                $this->convertedResponse->setRawResponse($this->response);
            }

            $this->isConverted = true;
        }

        return $this->convertedResponse;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->unwrap()->{$name}(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->unwrap()->{$name};
    }
}
