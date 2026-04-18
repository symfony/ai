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

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provides shared HTTP error handling for bridge result converters.
 *
 * Translates the common 4xx/429 responses returned by AI providers into
 * dedicated platform exceptions so consumers can react to auth failures,
 * bad requests and rate limits without parsing error bodies themselves.
 */
trait HttpStatusErrorHandlingTrait
{
    private function throwOnHttpError(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        if (401 === $status) {
            throw new AuthenticationException($this->extractErrorMessage($response) ?? 'Unauthorized');
        }

        if (400 === $status) {
            throw new BadRequestException($this->extractErrorMessage($response) ?? 'Bad Request');
        }

        if (429 === $status) {
            throw new RateLimitExceededException($this->extractRetryAfter($response));
        }
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        return $data['error']['message'] ?? $data['message'] ?? null;
    }

    private function extractRetryAfter(ResponseInterface $response): ?int
    {
        $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;

        return $retryAfter ? (int) $retryAfter : null;
    }
}