<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

/**
 * Normalizes a JSON Schema for VertexAI compatibility.
 *
 * - Removes 'additionalProperties' (not supported by VertexAI).
 * - Converts array-style nullable types ['string', 'null'] to
 *   ['type' => 'string', 'nullable' => true], which VertexAI's OpenAPI-flavored
 *   schema parser requires. Sent as-is, an array type triggers
 *   "Proto field is not repeating, cannot start list".
 *
 * Used both for tool parameter schemas and for structured-output response
 * schemas so the two stay in sync.
 *
 * @author Ben Younes <benyounes.ousama@gmail.com>
 */
final class SchemaNormalizer
{
    /**
     * @template T of array
     *
     * @param T $schema
     *
     * @return T
     */
    public static function normalize(array $schema): array
    {
        unset($schema['additionalProperties']);

        if (isset($schema['type']) && \is_array($schema['type'])) {
            $nullIndex = array_search('null', $schema['type'], true);
            if (false !== $nullIndex) {
                $types = $schema['type'];
                unset($types[$nullIndex]);
                $types = array_values($types);

                if (1 === \count($types)) {
                    $schema['type'] = $types[0];
                    $schema['nullable'] = true;
                }
            }
        }

        foreach ($schema as &$value) {
            if (\is_array($value)) {
                $value = self::normalize($value);
            }
        }

        return $schema;
    }
}
