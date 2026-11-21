<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command\Batch;

use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * Reads a value out of a decoded tool result.
 *
 * Syntax: `a.b` for fields, `[n]` for one list element, `[*]` for all elements and `[:n]` for
 * the first n. After `[*]` or `[:n]` the rest of the path is applied to each element.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PathResolver
{
    private const MAX_LISTED_KEYS = 10;

    /**
     * The envelope of {@see \Symfony\AI\Mate\Encoding\ResponseEncoder::encodeUntrusted()} is
     * transparent for paths, so callers never have to write `untrusted_data`.
     */
    public static function unwrap(mixed $result): mixed
    {
        if (\is_array($result) && \array_key_exists('untrusted_data', $result)) {
            return $result['untrusted_data'];
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException when the syntax is wrong
     */
    public static function assertValid(string $path): void
    {
        self::parse($path);
    }

    /**
     * @throws InvalidArgumentException when the syntax is wrong
     * @throws RuntimeException         when the data has no value at the path
     */
    public static function resolve(mixed $data, string $path): mixed
    {
        return self::walk($data, self::parse($path), '');
    }

    /**
     * @return list<array{string, string|int|null}> pairs of kind (field, index, all, slice) and argument
     */
    private static function parse(string $path): array
    {
        if ('' === $path) {
            throw new InvalidArgumentException('The path is empty.');
        }

        $segments = [];
        $offset = 0;
        $length = \strlen($path);
        while ($offset < $length) {
            if (preg_match('/\G\[(\d+)\]/', $path, $m, 0, $offset)) {
                $segments[] = ['index', (int) $m[1]];
            } elseif (preg_match('/\G\[\*\]/', $path, $m, 0, $offset)) {
                $segments[] = ['all', null];
            } elseif (preg_match('/\G\[:(\d+)\]/', $path, $m, 0, $offset)) {
                $segments[] = ['slice', (int) $m[1]];
            } elseif (preg_match('/\G\.?([^.\[\]]+)/', $path, $m, 0, $offset) && ($offset > 0 || !str_starts_with($path, '.'))) {
                $segments[] = ['field', $m[1]];
            } else {
                throw new InvalidArgumentException(\sprintf('Invalid path "%s": unexpected "%s" at position %d. Use "a.b", "[0]", "[*]" or "[:3]".', $path, $path[$offset], $offset));
            }
            $offset += \strlen($m[0]);
        }

        return $segments;
    }

    /**
     * @param list<array{string, string|int|null}> $segments
     */
    private static function walk(mixed $data, array $segments, string $walked): mixed
    {
        while ([] !== $segments) {
            [$kind, $arg] = array_shift($segments);

            if ('field' === $kind) {
                $name = (string) $arg;
                if (!\is_array($data)) {
                    throw new RuntimeException(\sprintf('"%s" is %s, so it has no field "%s".', '' === $walked ? 'the result' : $walked, self::describe($data), $name));
                }
                if (!\array_key_exists($name, $data)) {
                    throw new RuntimeException(\sprintf('"%s" has no field "%s". Available: %s.', '' === $walked ? 'the result' : $walked, $name, self::available($data)));
                }
                $data = $data[$name];
                $walked .= '' === $walked ? $name : '.'.$name;

                continue;
            }

            $label = '' === $walked ? 'the result' : $walked;
            if (!\is_array($data) || !array_is_list($data)) {
                throw new RuntimeException(\sprintf('"%s" is %s, not a list. Available: %s.', $label, self::describe($data), self::available($data)));
            }

            if ('index' === $kind) {
                if (!\array_key_exists((int) $arg, $data)) {
                    throw new RuntimeException([] === $data ? \sprintf('"%s" is empty. Available: empty list.', $label) : \sprintf('"%s" has no element %d. Available: %s.', $label, $arg, self::available($data)));
                }
                $data = $data[$arg];
                $walked .= '['.$arg.']';

                continue;
            }

            $items = 'slice' === $kind ? \array_slice($data, 0, (int) $arg) : $data;
            $out = [];
            foreach ($items as $i => $item) {
                $out[] = [] === $segments ? $item : self::walk($item, $segments, $walked.'['.$i.']');
            }

            return $out;
        }

        return $data;
    }

    private static function describe(mixed $data): string
    {
        return match (true) {
            null === $data => 'null',
            \is_array($data) => 'a list or object',
            default => 'a '.get_debug_type($data),
        };
    }

    private static function available(mixed $data): string
    {
        if (!\is_array($data)) {
            return 'none';
        }
        if ([] === $data) {
            return 'empty list';
        }
        if (array_is_list($data)) {
            return \sprintf('list with %d elements, indexes 0 to %d', \count($data), \count($data) - 1);
        }

        $keys = array_map(strval(...), array_keys($data));
        $shown = \array_slice($keys, 0, self::MAX_LISTED_KEYS);

        return implode(', ', $shown).(\count($keys) > self::MAX_LISTED_KEYS ? \sprintf(' (and %d more)', \count($keys) - self::MAX_LISTED_KEYS) : '');
    }
}
