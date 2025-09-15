<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class HttpErrorHandler
{
    public static function handleHttpError(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $errorMessage = self::extractErrorMessage($response);

        match ($statusCode) {
            401 => throw new AuthenticationException($errorMessage),
            404 => throw new NotFoundException($errorMessage),
            429 => throw new RateLimitExceededException(self::extractRetryAfter($response)),
            503 => throw new ServiceUnavailableException($errorMessage),
            default => throw new RuntimeException(\sprintf('HTTP %d: %s', $statusCode, $errorMessage)),
        };
    }

    private static function extractErrorMessage(ResponseInterface $response): string
    {
        $content = $response->getContent(false);

        if ('' === $content) {
            return \sprintf('HTTP %d error', $response->getStatusCode());
        }

        $data = json_decode($content, true);

        if (!\is_array($data)) {
            return $content;
        }

        if (isset($data['error']['message'])) {
            return $data['error']['message'];
        }

        if (isset($data['error']) && \is_string($data['error'])) {
            return $data['error'];
        }

        return $data['message'] ?? $data['detail'] ?? $content;
    }

    private static function extractRetryAfter(ResponseInterface $response): ?float
    {
        $headers = $response->getHeaders(false);

        if (isset($headers['retry-after'][0])) {
            return (float) $headers['retry-after'][0];
        }

        if (isset($headers['x-ratelimit-reset-requests'][0])) {
            return self::parseResetTime($headers['x-ratelimit-reset-requests'][0]);
        }

        if (isset($headers['x-ratelimit-reset-tokens'][0])) {
            return self::parseResetTime($headers['x-ratelimit-reset-tokens'][0]);
        }

        return null;
    }

    private static function parseResetTime(string $resetTime): ?float
    {
        if (is_numeric($resetTime)) {
            return (float) $resetTime;
        }

        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $resetTime, $matches)) {
            $hours = (int) ($matches[1] ?? 0);
            $minutes = (int) ($matches[2] ?? 0);
            $seconds = (int) ($matches[3] ?? 0);

            return (float) ($hours * 3600 + $minutes * 60 + $seconds);
        }

        $timestamp = strtotime($resetTime);
        if (false === $timestamp) {
            return null;
        }

        $diff = $timestamp - time();

        return $diff > 0 ? (float) $diff : null;
    }
}
