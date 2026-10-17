<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\SchemaNormalizer;

final class SchemaNormalizerTest extends TestCase
{
    /**
     * @param array<mixed> $schema
     * @param array<mixed> $expected
     */
    #[DataProvider('propertyOrderingDataProvider')]
    public function testPropertyOrdering(array $schema, array $expected)
    {
        $this->assertSame($expected, SchemaNormalizer::normalize($schema));
    }

    /**
     * @return iterable<string, array{0: array<mixed>, 1: array<mixed>}>
     */
    public static function propertyOrderingDataProvider(): iterable
    {
        yield 'object properties in declaration order' => [
            [
                'type' => 'object',
                'properties' => [
                    'pageTitle' => ['type' => 'string'],
                    'structure' => ['type' => 'string'],
                    'brief' => ['type' => 'string'],
                ],
                'required' => ['pageTitle', 'brief'],
                'additionalProperties' => false,
            ],
            [
                'type' => 'object',
                'properties' => [
                    'pageTitle' => ['type' => 'string'],
                    'structure' => ['type' => 'string'],
                    'brief' => ['type' => 'string'],
                ],
                'required' => ['pageTitle', 'brief'],
                'propertyOrdering' => ['pageTitle', 'structure', 'brief'],
            ],
        ];

        yield 'nullable object' => [
            [
                'type' => ['object', 'null'],
                'properties' => [
                    'b' => ['type' => 'string'],
                    'a' => ['type' => 'string'],
                ],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'b' => ['type' => 'string'],
                    'a' => ['type' => 'string'],
                ],
                'nullable' => true,
                'propertyOrdering' => ['b', 'a'],
            ],
        ];

        yield 'existing property ordering is kept' => [
            [
                'type' => 'object',
                'properties' => [
                    'a' => ['type' => 'string'],
                    'b' => ['type' => 'string'],
                ],
                'propertyOrdering' => ['b', 'a'],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'a' => ['type' => 'string'],
                    'b' => ['type' => 'string'],
                ],
                'propertyOrdering' => ['b', 'a'],
            ],
        ];

        yield 'numeric property names are strings' => [
            [
                'type' => 'object',
                'properties' => [
                    '1' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
            ],
            [
                'type' => 'object',
                'properties' => [
                    '1' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
                'propertyOrdering' => ['1', 'name'],
            ],
        ];

        yield 'property names matching schema keywords' => [
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string'],
                    'properties' => [
                        'type' => 'object',
                        'properties' => [
                            'y' => ['type' => 'string'],
                            'x' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string'],
                    'properties' => [
                        'type' => 'object',
                        'properties' => [
                            'y' => ['type' => 'string'],
                            'x' => ['type' => 'string'],
                        ],
                        'propertyOrdering' => ['y', 'x'],
                    ],
                ],
                'propertyOrdering' => ['type', 'properties'],
            ],
        ];

        yield 'nested object in properties' => [
            [
                'type' => 'object',
                'properties' => [
                    'user' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'age' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'user' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'age' => ['type' => 'integer'],
                        ],
                        'propertyOrdering' => ['name', 'age'],
                    ],
                ],
                'propertyOrdering' => ['user'],
            ],
        ];

        yield 'array of objects' => [
            [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'body' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'body' => ['type' => 'string'],
                    ],
                    'propertyOrdering' => ['title', 'body'],
                ],
            ],
        ];

        yield 'objects in anyOf and oneOf' => [
            [
                'anyOf' => [
                    [
                        'type' => 'object',
                        'properties' => [
                            'z' => ['type' => 'string'],
                            'y' => ['type' => 'string'],
                        ],
                    ],
                    ['type' => 'null'],
                ],
                'oneOf' => [
                    [
                        'type' => 'object',
                        'properties' => [
                            'm' => ['type' => 'string'],
                            'l' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'anyOf' => [
                    [
                        'type' => 'object',
                        'properties' => [
                            'z' => ['type' => 'string'],
                            'y' => ['type' => 'string'],
                        ],
                        'propertyOrdering' => ['z', 'y'],
                    ],
                    ['type' => 'null'],
                ],
                'oneOf' => [
                    [
                        'type' => 'object',
                        'properties' => [
                            'm' => ['type' => 'string'],
                            'l' => ['type' => 'string'],
                        ],
                        'propertyOrdering' => ['m', 'l'],
                    ],
                ],
            ],
        ];

        yield 'objects in $defs and definitions' => [
            [
                '$defs' => [
                    'Address' => [
                        'type' => 'object',
                        'properties' => [
                            'street' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                        ],
                    ],
                ],
                'definitions' => [
                    'Person' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                '$defs' => [
                    'Address' => [
                        'type' => 'object',
                        'properties' => [
                            'street' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                        ],
                        'propertyOrdering' => ['street', 'city'],
                    ],
                ],
                'definitions' => [
                    'Person' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                        ],
                        'propertyOrdering' => ['name', 'email'],
                    ],
                ],
            ],
        ];

        yield 'nested object without type' => [
            [
                'type' => 'object',
                'properties' => [
                    'meta' => [
                        'properties' => [
                            'b' => ['type' => 'string'],
                            'a' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'meta' => [
                        'properties' => [
                            'b' => ['type' => 'string'],
                            'a' => ['type' => 'string'],
                        ],
                    ],
                ],
                'propertyOrdering' => ['meta'],
            ],
        ];

        yield 'object without properties' => [
            ['type' => 'object', 'description' => 'Free-form'],
            ['type' => 'object', 'description' => 'Free-form'],
        ];

        yield 'object with empty properties' => [
            ['type' => 'object', 'properties' => []],
            ['type' => 'object', 'properties' => []],
        ];

        $emptyArrayObject = new \ArrayObject();
        yield 'properties as empty ArrayObject' => [
            ['type' => 'object', 'properties' => $emptyArrayObject],
            ['type' => 'object', 'properties' => $emptyArrayObject],
        ];

        $emptyStdClass = new \stdClass();
        yield 'properties as empty stdClass' => [
            ['type' => 'object', 'properties' => $emptyStdClass],
            ['type' => 'object', 'properties' => $emptyStdClass],
        ];
    }
}
