<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Validator;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author sadiq<sadiqali94@gmail.com
 */
final class AlbertValidator
{
    public static function validateUrl(string $baseUrl): void
    {
        self::validateNotEmptyBaseUrl($baseUrl);
        self::validateScheme($baseUrl);
        self::validateNoTrailingSlash($baseUrl);
        self::validateApiVersion($baseUrl);
    }

    public static function validateApi(string $apiKey, string $baseUrl): void
    {
        self::validateApiKey($apiKey);
        self::validateNotEmptyBaseUrl($baseUrl);
    }

    private static function validateApiKey(string $apiKey): void
    {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }
    }

    private static function validateNotEmptyBaseUrl(string $baseUrl): void
    {
        if ('' === $baseUrl) {
            throw new InvalidArgumentException('The base URL must not be empty.');
        }
    }

    private static function validateScheme(string $baseUrl): void
    {
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('The Albert URL must start with "https://".');
        }
    }

    private static function validateNoTrailingSlash(string $baseUrl): void
    {
        if (str_ends_with($baseUrl, '/')) {
            throw new InvalidArgumentException('The Albert URL must not end with a trailing slash.');
        }
    }

    private static function validateApiVersion(string $baseUrl): void
    {
        if (!preg_match('/\/v\d+$/', $baseUrl)) {
            throw new InvalidArgumentException('The Albert URL must include an API version (e.g., /v1, /v2).');
        }
    }
}
