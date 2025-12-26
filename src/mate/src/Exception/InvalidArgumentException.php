<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Exception;

/**
 * Base exception class for invalid argument errors in the Mate component.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param array<string> $available
     */
    public static function forExtensionNotFound(string $extension, array $available): self
    {
        return new self(\sprintf(
            'Extension "%s" not found. Available: %s',
            $extension,
            implode(', ', $available)
        ));
    }

    /**
     * @param array<string> $validTypes
     */
    public static function forInvalidType(string $type, array $validTypes): self
    {
        return new self(\sprintf(
            'Invalid type "%s". Valid types: %s',
            $type,
            implode(', ', $validTypes)
        ));
    }
}
