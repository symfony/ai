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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\MethodDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PropertyInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaAttributeDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SerializerDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\TypeInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactory;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ColorProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ConflictDto;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ContextAwareProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\SearchQueryDto;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\StatusProvider;

final class SchemaProviderFactoryIntegrationTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $describer = new Describer([
            new SerializerDescriber(),
            new TypeInfoDescriber(),
            new MethodDescriber(),
            new PropertyInfoDescriber(),
            new SchemaAttributeDescriber([
                StatusProvider::class => new StatusProvider(['active', 'archived']),
                ColorProvider::class => new ColorProvider(['red', 'blue']),
                ContextAwareProvider::class => new ContextAwareProvider(),
            ]),
        ]);

        $this->factory = new Factory($describer);
    }

    public function testBuildParametersAppliesProviderOnToolMethodSignature()
    {
        $schema = $this->factory->buildParameters(SearchQueryDto::class, 'search');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'archived'],
                ],
                'color' => [
                    'type' => 'string',
                    'enum' => ['red', 'blue'],
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => ['foo', 'bar'],
                ],
                'query' => [
                    'type' => 'string',
                    'minLength' => 3,
                ],
            ],
            'required' => ['status', 'color', 'category', 'query'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function testBuildPropertiesAppliesProviderOnDtoForStructuredOutput()
    {
        $schema = $this->factory->buildProperties(SearchQueryDto::class);

        $this->assertSame(['active', 'archived'], $schema['properties']['status']['enum']);
        $this->assertSame(['red', 'blue'], $schema['properties']['color']['enum']);
        $this->assertSame(3, $schema['properties']['query']['minLength']);
    }

    public function testRuntimeProviderWinsOverStaticEnumOnConflict()
    {
        // ConflictDto: #[Schema(enum: ['a','b'], provider: StatusProvider::class)] - provider applied after
        // the static fragment via array_replace_recursive, so the runtime values override.
        $schema = $this->factory->buildProperties(ConflictDto::class);

        $this->assertSame(['active', 'archived'], $schema['properties']['status']['enum']);
    }

    public function testResponseFormatFactoryProducesStructuredOutputSchemaWithRuntimeEnum()
    {
        $responseFormat = (new ResponseFormatFactory($this->factory))->create(SearchQueryDto::class);

        $this->assertSame('json_schema', $responseFormat['type']);
        $this->assertSame('SearchQueryDto', $responseFormat['json_schema']['name']);
        $this->assertTrue($responseFormat['json_schema']['strict']);

        $properties = $responseFormat['json_schema']['schema']['properties'];
        $this->assertSame(['active', 'archived'], $properties['status']['enum']);
        $this->assertSame(['red', 'blue'], $properties['color']['enum']);
        $this->assertSame(['foo', 'bar'], $properties['category']['enum']);
    }
}
