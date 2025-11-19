<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Oskar Stark <oskar.stark@sensiolabs.de>
 */
abstract class AbstractModelClient
{
    protected static function getBaseUrl(?string $region): string
    {
        return match ($region) {
            null => 'https://api.openai.com',
            PlatformFactory::REGION_EU => 'https://eu.api.openai.com',
            PlatformFactory::REGION_US => 'https://us.api.openai.com',
            default => throw new InvalidArgumentException(\sprintf('Invalid region "%s". Valid options are: "%s", "%s", or null.', $region, PlatformFactory::REGION_EU, PlatformFactory::REGION_US)),
        };
    }

    protected static function validateApiKey(string $apiKey): void
    {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        if (!str_starts_with($apiKey, 'sk-')) {
            throw new InvalidArgumentException('The API key must start with "sk-".');
        }
    }
}
