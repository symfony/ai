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
 * Replaces `{{id.path}}` expressions in the params or the resource URI of a batch call.
 *
 * A string that is exactly one expression keeps the type of the referenced value. Inside a
 * longer string the value is inserted as text. `\{{` yields a literal `{{`.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ReferenceResolver
{
    private const TOKEN = '/\\\\\{\{|\{\{\s*([a-z][a-z0-9_]*)((?:[.\[][^{}]*?)?)\s*\}\}|\{\{/';

    /**
     * Lists every reference in a value, so the caller can check them before anything runs.
     *
     * @return list<array{id: string, path: string, expression: string}>
     *
     * @throws InvalidArgumentException when an expression is malformed
     */
    public static function collect(mixed $value): array
    {
        if (\is_array($value)) {
            $found = [];
            foreach ($value as $item) {
                array_push($found, ...self::collect($item));
            }

            return $found;
        }

        if (!\is_string($value)) {
            return [];
        }

        $found = [];
        foreach (self::tokenize($value) as $token) {
            if (\is_array($token)) {
                $found[] = $token;
            }
        }

        return $found;
    }

    /**
     * @param array<string, mixed>  $results raw results of finished calls, keyed by call id
     * @param array<string, string> $failed  error messages of failed calls, keyed by call id
     *
     * @throws RuntimeException when a reference cannot be resolved
     */
    public static function resolve(mixed $value, array $results, array $failed = []): mixed
    {
        if (\is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::resolve($item, $results, $failed), $value);
        }

        if (!\is_string($value)) {
            return $value;
        }

        $tokens = self::tokenize($value);
        if (1 === \count($tokens) && \is_array($tokens[0])) {
            return self::lookup($tokens[0], $results, $failed);
        }

        $text = '';
        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $text .= $token;

                continue;
            }

            $resolved = self::lookup($token, $results, $failed);
            if (!\is_scalar($resolved)) {
                throw new RuntimeException(\sprintf('Reference %s is %s and cannot be inserted into a longer string. Use it as the whole value instead.', $token['expression'], null === $resolved ? 'null' : 'a list or object'));
            }
            $text .= \is_bool($resolved) ? ($resolved ? 'true' : 'false') : (string) $resolved;
        }

        return $text;
    }

    /**
     * @return list<string|array{id: string, path: string, expression: string}>
     */
    private static function tokenize(string $value): array
    {
        $tokens = [];
        $offset = 0;
        preg_match_all(self::TOKEN, $value, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            [$whole, $position] = $match[0];
            if ($position > $offset) {
                $tokens[] = substr($value, $offset, $position - $offset);
            }
            $offset = $position + \strlen($whole);

            if ('\{{' === $whole) {
                $tokens[] = '{{';
            } elseif ('{{' === $whole) {
                throw new InvalidArgumentException(\sprintf('Invalid reference in "%s": expected "{{id.path}}" with a lowercase id. Write "\{{" for a literal "{{".', $value));
            } else {
                $path = str_starts_with($match[2][0], '.') ? substr($match[2][0], 1) : $match[2][0];
                if ('' !== $path) {
                    PathResolver::assertValid($path);
                }
                $tokens[] = ['id' => $match[1][0], 'path' => $path, 'expression' => $whole];
            }
        }
        if ($offset < \strlen($value)) {
            $tokens[] = substr($value, $offset);
        }

        return $tokens;
    }

    /**
     * @param array{id: string, path: string, expression: string} $reference
     * @param array<string, mixed>                                $results
     * @param array<string, string>                               $failed
     */
    private static function lookup(array $reference, array $results, array $failed): mixed
    {
        $id = $reference['id'];
        if (isset($failed[$id])) {
            throw new RuntimeException(\sprintf('Reference %s cannot be resolved: %s', $reference['expression'], $failed[$id]));
        }
        if (!\array_key_exists($id, $results)) {
            throw new RuntimeException(\sprintf('Reference %s cannot be resolved: call "%s" has no result.', $reference['expression'], $id));
        }

        $data = PathResolver::unwrap($results[$id]);
        if ('' === $reference['path']) {
            return $data;
        }

        try {
            return PathResolver::resolve($data, $reference['path']);
        } catch (RuntimeException $e) {
            throw new RuntimeException(\sprintf('Reference %s cannot be resolved: %s', $reference['expression'], $e->getMessage()), 0, $e);
        }
    }
}
