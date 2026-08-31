<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput;

use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactoryInterface;

/**
 * Test double for a factory deriving the schema from the runtime state of the instance to populate:
 * only the properties still holding null are described, so two instances of the same class with
 * different values filled in produce different schemas.
 */
final class MissingPropertiesResponseFormatFactory implements ResponseFormatFactoryInterface
{
    public function create(string|object $response): array
    {
        $reflection = new \ReflectionClass($response);

        $properties = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (\is_object($response) && null !== $property->getValue($response)) {
                continue;
            }

            $properties[$property->getName()] = ['type' => 'string'];
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $reflection->getShortName(),
                'schema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => array_keys($properties),
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ];
    }
}
