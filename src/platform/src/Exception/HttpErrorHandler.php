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
            503 => throw new ServiceUnavailableException($errorMessage),
            default => throw new RuntimeException(\sprintf('HTTP %d: %s', $statusCode, $errorMessage)),
        };
    }

    private static function extractErrorMessage(ResponseInterface $response): string
    {
        try {
            $content = $response->getContent(false);

            if ('' === $content) {
                return \sprintf('HTTP %d error', $response->getStatusCode());
            }

            $data = json_decode($content, true);

            if (null === $data || !\is_array($data)) {
                return \sprintf('HTTP %d error', $response->getStatusCode());
            }

            if (isset($data['error']['message'])) {
                return $data['error']['message'];
            }

            if (isset($data['detail'])) {
                return $data['detail'];
            }

            return $content;
        } catch (\Throwable) {
            try {
                $content = $response->getContent(false);

                return !empty($content) ? $content : \sprintf('HTTP %d error', $response->getStatusCode());
            } catch (\Throwable) {
                return \sprintf('HTTP %d error', $response->getStatusCode());
            }
        }
    }
}
