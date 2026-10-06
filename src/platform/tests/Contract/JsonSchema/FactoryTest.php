<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolOptionalParam;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithBackedEnums;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithObjectAccessors;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithToolParameterAttribute;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Selector\MissingPropertiesSelector;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ExampleDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\GroupedDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\NestedGroupedDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListOfPolymorphicTypesDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SchemaAttributeValuesDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Step;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Ticket;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Trip;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UnionType\UnionTypeDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithAccessors;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

final class FactoryTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Factory();
    }

    protected function tearDown(): void
    {
        unset($this->factory);
    }

    public function testBuildParametersDefinitionRequired()
    {
        $actual = $this->factory->buildParameters(ToolRequiredParams::class, 'bar');
        $expected = [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => 'The text given to the tool',
                ],
                'number' => [
                    'type' => 'integer',
                    'description' => 'A number given to the tool',
                ],
            ],
            'required' => ['text', 'number'],
            'additionalProperties' => false,
        ];

        $this->assertSame($expected, $actual);
    }

    public function testBuildParametersDefinitionRequiredWithAdditionalToolParameterAttribute()
    {
        $actual = $this->factory->buildParameters(ToolWithToolParameterAttribute::class, '__invoke');
        $expected = [
            'type' => 'object',
            'properties' => [
                'animal' => [
                    'type' => 'string',
                    'description' => 'The animal given to the tool',
                    'enum' => ['dog', 'cat', 'bird'],
                ],
                'numberOfArticles' => [
                    'type' => 'integer',
                    'description' => 'The number of articles given to the tool',
                    'const' => 42,
                ],
                'infoEmail' => [
                    'type' => 'string',
                    'description' => 'The info email given to the tool',
                    'const' => 'info@example.de',
                ],
                'locales' => [
                    'type' => 'string',
                    'description' => 'The locales given to the tool',
                    'const' => ['de', 'en'],
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'The text given to the tool',
                    'pattern' => '^[a-zA-Z]+$',
                    'minLength' => 1,
                    'maxLength' => 10,
                ],
                'number' => [
                    'type' => 'integer',
                    'description' => 'The number given to the tool',
                    'minimum' => 1,
                    'maximum' => 10,
                    'multipleOf' => 2,
                    'exclusiveMinimum' => 1,
                    'exclusiveMaximum' => 10,
                ],
                'products' => [
                    'type' => 'array',
                    'description' => 'The products given to the tool',
                    'minItems' => 1,
                    'maxItems' => 10,
                    'uniqueItems' => true,
                    'minContains' => 1,
                    'maxContains' => 10,
                ],
                'shippingAddress' => [
                    'type' => 'string',
                    'description' => 'The shipping address given to the tool',
                    'minProperties' => 1,
                    'maxProperties' => 10,
                    'dependentRequired' => true,
                ],
            ],
            'required' => [
                'animal',
                'numberOfArticles',
                'infoEmail',
                'locales',
                'text',
                'number',
                'products',
                'shippingAddress',
            ],
            'additionalProperties' => false,
        ];

        $this->assertSame($expected, $actual);
    }

    public function testBuildParametersDefinitionOptional()
    {
        $actual = $this->factory->buildParameters(ToolOptionalParam::class, 'bar');
        $expected = [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => 'The text given to the tool',
                ],
                'number' => [
                    'type' => 'integer',
                    'description' => 'A number given to the tool',
                ],
            ],
            'required' => ['text'],
            'additionalProperties' => false,
        ];

        $this->assertSame($expected, $actual);
    }

    public function testBuildParametersDefinitionNone()
    {
        $actual = $this->factory->buildParameters(ToolNoParams::class, '__invoke');

        $this->assertNull($actual);
    }

    public function testBuildPropertiesForUserClass()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => [
                    'type' => 'string',
                    'description' => 'The name of the user in lowercase',
                ],
                'createdAt' => [
                    'type' => 'string',
                    'format' => 'date-time',
                ],
                'isActive' => ['type' => 'boolean'],
                'age' => ['type' => ['integer', 'null']],
            ],
            'required' => ['id', 'name', 'createdAt', 'isActive', 'age'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(User::class);

        $this->assertSame($expected, $actual);
    }

    public function testBuildPropertiesHonorsPromotedConstructorDefault()
    {
        $actual = $this->factory->buildProperties(UserWithConstructor::class);

        $this->assertNotNull($actual);
        $this->assertSame(['id', 'name', 'createdAt', 'isActive'], $actual['required']);
        $this->assertSame(['type' => ['integer', 'null']], $actual['properties']['age']);
    }

    public function testBuildPropertiesHonorsNonPromotedConstructorDefaults()
    {
        $dto = new class('continue') {
            public readonly ?string $task;
            public readonly ?string $artifactId;

            public function __construct(?string $task, ?string $artifactId = null)
            {
                $this->task = $task;
                $this->artifactId = $artifactId;
            }
        };

        $actual = $this->factory->buildProperties($dto::class);

        $this->assertNotNull($actual);
        $this->assertSame(['task'], $actual['required']);
        $this->assertSame(['type' => ['string', 'null']], $actual['properties']['artifactId']);
    }

    public function testBuildPropertiesWithOnlyOptionalFieldsRejectsUnknownProperties()
    {
        $dto = new class {
            public function __construct(public readonly ?string $artifactId = null)
            {
            }
        };

        $actual = $this->factory->buildProperties($dto::class);

        $this->assertNotNull($actual);
        $this->assertArrayNotHasKey('required', $actual);
        $this->assertFalse($actual['additionalProperties']);
    }

    public function testBuildPropertiesForMathReasoningClass()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'explanation' => ['type' => 'string'],
                            'output' => ['type' => 'string'],
                        ],
                        'required' => ['explanation', 'output'],
                        'additionalProperties' => false,
                    ],
                ],
                'finalAnswer' => ['type' => 'string'],
                'result' => ['type' => 'number'],
            ],
            'required' => ['steps', 'finalAnswer', 'result'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(MathReasoning::class);

        $this->assertSame($expected, $actual);
    }

    public function testBuildPropertiesForListOfPolymorphicTypesDto()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'anyOf' => [
                            [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'type' => [
                                        'type' => 'string',
                                        'pattern' => '^name$',
                                        'enum' => ['name'],
                                    ],
                                ],
                                'required' => [
                                    'name',
                                    'type',
                                ],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'age' => ['type' => 'integer'],
                                    'type' => [
                                        'type' => 'string',
                                        'pattern' => '^age$',
                                        'enum' => ['age'],
                                    ],
                                ],
                                'required' => [
                                    'age',
                                    'type',
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'required' => ['items'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(ListOfPolymorphicTypesDto::class);

        $this->assertSame($expected, $actual);
        $this->assertSame($expected['type'], $actual['type']);
        $this->assertSame($expected['required'], $actual['required']);
    }

    public function testBuildPropertiesForUnionTypeDto()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'time' => [
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'readableTime' => ['type' => 'string'],
                            ],
                            'required' => ['readableTime'],
                            'additionalProperties' => false,
                        ],
                        [
                            'type' => 'object',
                            'properties' => [
                                'timestamp' => ['type' => 'integer'],
                            ],
                            'required' => ['timestamp'],
                            'additionalProperties' => false,
                        ],
                        [
                            'type' => 'null',
                        ],
                    ],
                ],
            ],
            'required' => ['time'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(UnionTypeDto::class);

        $this->assertSame($expected, $actual);
        $this->assertSame($expected['type'], $actual['type']);
        $this->assertSame($expected['required'], $actual['required']);
    }

    public function testBuildPropertiesForStepClass()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'explanation' => ['type' => 'string'],
                'output' => ['type' => 'string'],
            ],
            'required' => ['explanation', 'output'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(Step::class);

        $this->assertSame($expected, $actual);
    }

    public function testBuildPropertiesForExampleDto()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
                'taxRate' => [
                    'type' => 'integer',
                    'enum' => [7, 19],
                ],
                'category' => [
                    'type' => ['string', 'null'],
                    'enum' => ['Foo', 'Bar', null],
                ],
                'quantity' => [
                    'type' => ['string', 'null'],
                    'description' => 'The quantity of the ingredient',
                    'example' => '2 cups',
                ],
            ],
            'required' => ['name', 'taxRate', 'category'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(ExampleDto::class);

        $this->assertSame($expected, $actual);
    }

    public function testBuildPropertiesAttributeValuesWin()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'This is the attribute description.',
                    'example' => 'Attribute example',
                ],
            ],
            'required' => ['name'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(SchemaAttributeValuesDto::class);

        $this->assertSame($expected, $actual);
    }

    public function testBuildParametersWithBackedEnums()
    {
        $actual = $this->factory->buildParameters(ToolWithBackedEnums::class, '__invoke');
        $expected = [
            'type' => 'object',
            'properties' => [
                'searchTerms' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'The search terms',
                ],
                'mode' => [
                    'type' => 'string',
                    'enum' => ['and', 'or', 'not'],
                    'description' => 'The search mode',
                ],
                'priority' => [
                    'type' => 'integer',
                    'enum' => [1, 5, 10],
                    'description' => 'The search priority',
                ],
                'fallback' => [
                    'type' => ['string', 'null'],
                    'enum' => ['and', 'or', 'not'],
                    'description' => 'Optional fallback mode',
                ],
            ],
            'required' => ['searchTerms', 'mode', 'priority'],
            'additionalProperties' => false,
        ];

        $this->assertSame($expected, $actual);
    }

    public function testBuildParametersWithObjectAccessors()
    {
        $actual = $this->factory->buildParameters(ToolWithObjectAccessors::class, '__invoke');
        $expected = [
            'type' => 'object',
            'properties' => [
                'object' => [
                    'type' => 'object',
                    'properties' => [
                        'value1' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                        'value2' => [
                            'type' => 'number',
                            'const' => 42,
                        ],
                        'value3' => [
                            'type' => 'string',
                            'pattern' => '^foo$',
                        ],
                    ],
                    'required' => [
                        'value1',
                        'value2',
                        'value3',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['object'],
            'additionalProperties' => false,
        ];

        $this->assertSame($expected, $actual);
    }

    public function testBuildPropertiesWithoutSerializerGroupsIncludesAllProperties()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => ['integer', 'null']],
                'slug' => ['type' => 'string'],
                'internal' => ['type' => 'string'],
            ],
            'required' => ['name', 'age', 'slug', 'internal'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(GroupedDto::class);

        $this->assertSame($expected, $actual);
    }

    public function testBuildPropertiesScopedToSerializerGroups()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => ['integer', 'null']],
            ],
            'required' => ['name', 'age'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(GroupedDto::class, ['serializer_groups' => ['write']]);

        $this->assertSame($expected, $actual);
    }

    public function testBuildPropertiesScopedToMultipleSerializerGroups()
    {
        $actual = $this->factory->buildProperties(GroupedDto::class, ['serializer_groups' => ['read', 'write']]);

        $this->assertSame(['name', 'age', 'slug'], array_keys($actual['properties']));
    }

    public function testBuildPropertiesPropagatesSerializerGroupsIntoNestedObjects()
    {
        $actual = $this->factory->buildProperties(NestedGroupedDto::class, ['serializer_groups' => ['write']]);

        $this->assertSame(['title', 'child'], array_keys($actual['properties']));
        $this->assertSame(['name', 'age'], array_keys($actual['properties']['child']['properties']));
    }

    public function testBuildPropertiesForInstanceDescribesOnlyMissingProperties()
    {
        // Uninitialized and null properties are missing
        $user = new User();
        $this->assertSame(['id', 'name', 'createdAt', 'isActive', 'age'], array_keys($this->factory->buildProperties(User::class, $this->onlyMissingOf($user))['properties']));

        $user->id = 1;
        $user->name = 'john';
        $user->isActive = false;
        $expected = [
            'type' => 'object',
            'properties' => [
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                'age' => ['type' => ['integer', 'null']],
            ],
            'required' => ['createdAt', 'age'],
            'additionalProperties' => false,
        ];

        $this->assertSame($expected, $this->factory->buildProperties(User::class, $this->onlyMissingOf($user)));
    }

    public function testBuildPropertiesForInstanceReadsPrivatePropertiesBehindAccessors()
    {
        $user = new UserWithAccessors();
        $user->setId(1);
        $user->setIsActive(true);

        $actual = $this->factory->buildProperties(UserWithAccessors::class, $this->onlyMissingOf($user));

        $this->assertSame(['name', 'createdAt', 'age'], array_keys($actual['properties']));
    }

    public function testBuildPropertiesForInstanceSkipsPropertiesItCannotWrite()
    {
        // The readonly code can only be set through the constructor, which populating an existing instance never calls
        $actual = $this->factory->buildProperties(Ticket::class, $this->onlyMissingOf(new Ticket()));

        $this->assertSame(['seat'], array_keys($actual['properties']));
    }

    public function testBuildPropertiesForInstanceNarrowsNestedObjects()
    {
        $trip = new Trip(destination: new City(name: 'Berlin', country: 'Germany'));

        $actual = $this->factory->buildProperties(Trip::class, $this->onlyMissingOf($trip));

        $this->assertSame(['title', 'destination', 'origin'], array_keys($actual['properties']));
        // Partially filled: only what is still missing on it, and no longer nullable
        $this->assertSame(['population', 'mayor'], array_keys($actual['properties']['destination']['properties']));
        $this->assertSame('object', $actual['properties']['destination']['type']);
        // Not set at all: the full class
        $this->assertSame(['name', 'population', 'country', 'mayor'], array_keys($actual['properties']['origin']['properties']));
        $this->assertSame(['object', 'null'], $actual['properties']['origin']['type']);
    }

    public function testBuildPropertiesForInstanceSkipsNestedObjectsWithNothingMissing()
    {
        $trip = new Trip(destination: new City(name: 'Berlin', population: 3500000, country: 'Germany', mayor: 'Kai Wegner'));

        $actual = $this->factory->buildProperties(Trip::class, $this->onlyMissingOf($trip));

        $this->assertSame(['title', 'origin'], array_keys($actual['properties']));
    }

    public function testBuildPropertiesForInstanceSkipsFilledCollectionsAndScalars()
    {
        // A non-empty collection, an empty string and a zero are all filled: nothing is left to describe
        $this->assertNull($this->factory->buildProperties(MathReasoning::class, $this->onlyMissingOf(new MathReasoning([new Step('a', 'b')], '', 0.0))));

        // An empty collection is missing and is described with its full item schema
        $actual = $this->factory->buildProperties(MathReasoning::class, $this->onlyMissingOf(new MathReasoning([], '', 0.0)));

        $this->assertSame(['steps'], array_keys($actual['properties']));
        $this->assertSame(['explanation', 'output'], array_keys($actual['properties']['steps']['items']['properties']));
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyMissingOf(object $instance): array
    {
        return [Factory::CONTEXT_SELECTOR => new MissingPropertiesSelector($instance)];
    }
}
