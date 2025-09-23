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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Fixtures\StructuredOutput\ExampleDto;
use Symfony\AI\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Fixtures\StructuredOutput\PolymorphicType\ListOfPolymorphicTypesDto;
use Symfony\AI\Fixtures\StructuredOutput\Step;
use Symfony\AI\Fixtures\StructuredOutput\UnionType\UnionTypeDto;
use Symfony\AI\Fixtures\StructuredOutput\User;
use Symfony\AI\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Fixtures\Tool\ToolOptionalParam;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Fixtures\Tool\ToolWithBackedEnums;
use Symfony\AI\Fixtures\Tool\ToolWithToolParameterAttribute;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\AI\Platform\Contract\JsonSchema\DescriptionParser;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;

#[CoversClass(Factory::class)]
#[UsesClass(With::class)]
#[UsesClass(DescriptionParser::class)]
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
                    'items' => ['type' => 'string'],
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
            'required' => ['id', 'name', 'createdAt', 'isActive'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(User::class);

        $this->assertSame($expected, $actual);
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
                'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'finalAnswer' => ['type' => 'string'],
            ],
            'required' => ['steps', 'confidence', 'finalAnswer'],
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
            'required' => [],
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
                'name' => ['type' => 'string'],
                'taxRate' => [
                    'type' => 'integer',
                    'enum' => [7, 19],
                ],
                'category' => [
                    'type' => ['string', 'null'],
                    'enum' => ['Foo', 'Bar', null],
                ],
            ],
            'required' => ['name', 'taxRate'],
            'additionalProperties' => false,
        ];

        $actual = $this->factory->buildProperties(ExampleDto::class);

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
}
