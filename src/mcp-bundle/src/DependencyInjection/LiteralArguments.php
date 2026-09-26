<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;

/**
 * Prepares attribute metadata for service definitions: escapes "%" so the container does not read it
 * as a parameter placeholder, and clones inline definitions so no two method calls share an instance.
 *
 * @internal
 */
final class LiteralArguments
{
    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return array<array-key, mixed>
     */
    public static function escape(array $arguments): array
    {
        $escaped = [];
        foreach ($arguments as $key => $argument) {
            $escaped[\is_string($key) ? self::escapeString($key) : $key] = self::escapeValue($argument);
        }

        return $escaped;
    }

    private static function escapeValue(mixed $value): mixed
    {
        if (\is_string($value)) {
            return self::escapeString($value);
        }

        if (\is_array($value)) {
            return self::escape($value);
        }

        if ($value instanceof Definition) {
            $copy = clone $value;
            $copy->setArguments(self::escape($value->getArguments()));
            // Property names are dumped verbatim, so only their values are escaped.
            $copy->setProperties(array_map(self::escapeValue(...), $value->getProperties()));

            return $copy;
        }

        return $value;
    }

    private static function escapeString(string $value): string
    {
        return str_replace('%', '%%', $value);
    }
}
