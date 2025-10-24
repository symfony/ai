<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Floran Pagliai <floran.pagliai@gmail.com>
 */
trait ResultConverterStatusExceptionTrait
{
    /**
     * Handle HTTP status codes and throw appropriate exceptions.
     *
     * @throws AuthenticationException When status code is 401
     * @throws RateLimitExceededException When status code is 429
     * @throws RuntimeException For other error status codes
     */
    protected function validateStatusCode(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if (200 <= $statusCode && 300 > $statusCode) {
            return;
        }

        switch ($statusCode) {
            case 401:
                $this->handleAuthenticationError($response);
            case 429:
                $this->handleRateLimitExceeded($response);
            default:
                $this->handleGenericError($response);
        }
    }

    protected function handleAuthenticationError(ResponseInterface $response): void
    {
        $message = $this->extractErrorMessage($response, 'Authentication failed');
        throw new AuthenticationException($message);
    }

    protected function handleRateLimitExceeded(ResponseInterface $response): void
    {
        $retryAfter = $this->extractRetryAfterValue($response);
        // https://github.com/symfony/ai/pull/538
//        throw new RateLimitExceededException($retryAfter);
    }

    protected function handleGenericError(ResponseInterface $response): void
    {
        $message = $this->extractErrorMessage($response, 'API error: ' . $response->getStatusCode());
        throw new RuntimeException($message);
    }

    protected function extractRetryAfterValue(ResponseInterface $response): ?float
    {
        $headers = $response->getHeaders(false);

        if (isset($headers['retry-after'][0])) {
            return (float) $headers['retry-after'][0];
        }

        return null;
    }

    protected function extractErrorMessage(ResponseInterface $response, string $defaultMessage): string
    {
        try {
            $data = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);

            if (isset($data['error']['message'])) {
                return $data['error']['message'];
            }

            if (isset($data['message'])) {
                return $data['message'];
            }

            if (isset($data['error']) && is_string($data['error'])) {
                return $data['error'];
            }

            if (isset($data['error_description'])) {
                return $data['error_description'];
            }
        } catch (\Throwable) {
            // Fallback to default message if JSON parsing fails
        }

        return $defaultMessage;
    }
}
