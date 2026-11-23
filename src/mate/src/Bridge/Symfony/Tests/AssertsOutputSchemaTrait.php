<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests;

/**
 * Checks a decoded result against the output schema a capability declares, so a schema cannot
 * drift from what the code returns. Supports the subset the schemas use: type, properties,
 * required, additionalProperties: false, items, enum, oneOf and anyOf.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait AssertsOutputSchemaTrait
{
    /**
     * @param array<string, mixed> $schema
     */
    private function assertMatchesOutputSchema(array $schema, mixed $data, string $message = ''): void
    {
        $errors = self::schemaErrors($schema, $data, '$');

        $this->assertSame([], $errors, $message.' Result does not match its output schema: '.implode('; ', $errors));
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<string>
     */
    private static function schemaErrors(array $schema, mixed $data, string $path): array
    {
        if (isset($schema['oneOf']) || isset($schema['anyOf'])) {
            $branches = $schema['oneOf'] ?? $schema['anyOf'];
            foreach ($branches as $branch) {
                if ([] === self::schemaErrors($branch, $data, $path)) {
                    return [];
                }
            }

            return [$path.' matches none of the '.(isset($schema['oneOf']) ? 'oneOf' : 'anyOf').' branches'];
        }

        $errors = [];

        if (isset($schema['enum']) && !\in_array($data, $schema['enum'], true)) {
            $errors[] = $path.' is not one of the enum values';
        }

        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $actual = match (true) {
                null === $data => 'null',
                \is_bool($data) => 'boolean',
                \is_int($data) => 'integer',
                \is_float($data) => 'number',
                \is_string($data) => 'string',
                \is_array($data) && array_is_list($data) => 'array',
                default => 'object',
            };
            $matches = \in_array($actual, $types, true) || ('integer' === $actual && \in_array('number', $types, true)) || ([] === $data && \in_array('object', $types, true));
            if (!$matches) {
                return [\sprintf('%s is %s, expected %s', $path, $actual, implode('|', $types))];
            }
        }

        if (\is_array($data) && !array_is_list($data)) {
            foreach ($schema['required'] ?? [] as $key) {
                if (!\array_key_exists($key, $data)) {
                    $errors[] = \sprintf('%s lacks required "%s"', $path, $key);
                }
            }
            foreach ($data as $key => $value) {
                if (isset($schema['properties'][$key])) {
                    $errors = [...$errors, ...self::schemaErrors($schema['properties'][$key], $value, $path.'.'.$key)];
                } elseif (false === ($schema['additionalProperties'] ?? true)) {
                    $errors[] = \sprintf('%s has undeclared "%s"', $path, $key);
                }
            }
        }

        if (\is_array($data) && array_is_list($data) && isset($schema['items'])) {
            foreach ($data as $i => $item) {
                $errors = [...$errors, ...self::schemaErrors($schema['items'], $item, $path.'['.$i.']')];
            }
        }

        return $errors;
    }
}
