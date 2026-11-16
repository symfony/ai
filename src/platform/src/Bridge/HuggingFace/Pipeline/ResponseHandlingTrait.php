<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Pipeline;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait ResponseHandlingTrait
{
    use HttpStatusErrorHandlingTrait;

    /**
     * @return array<mixed>
     */
    private function getResponseData(RawResultInterface|RawHttpResult $result): array
    {
        $response = $result->getObject();
        \assert($response instanceof ResponseInterface);

        $this->assertSuccessfulResponse($response);

        return $result->getData();
    }

    private function assertSuccessfulResponse(ResponseInterface $response): void
    {
        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $response->getStatusCode(), $this->extractErrorMessage($response) ?? $response->getContent(false)));
        }
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        // hf-inference reports errors as a plain string or a list of strings
        $error = $data['error'] ?? null;
        if (\is_array($error)) {
            $error = $error[0] ?? $error['message'] ?? null;
        }

        return \is_string($error) ? $error : null;
    }
}
