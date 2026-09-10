<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\MappedRecipe;
use Symfony\AI\Agent\Tests\Fixtures\Tool\Recipe;
use Symfony\AI\Agent\Tests\Fixtures\Tool\StatusProvider;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolDate;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithConstraints;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithInvalidMappedArguments;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithMappedArguments;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithNullableMappedArguments;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithScalarMappedArguments;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\EventListener\ValidateToolCallArgumentsListener;
use Symfony\AI\Agent\Toolbox\Exception\InvalidToolCallArgumentsException;
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\MethodDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\PropertyInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaAttributeDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SerializerDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\TypeInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ValidatorConstraintsDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

class MapToolArgumentsTest extends TestCase
{
    public function testFactoryExposesFlatDtoSchema()
    {
        $tool = $this->firstTool(new ReflectionToolFactory($this->factoryWithProviders()), ToolWithMappedArguments::class);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'ingredient' => [
                    'type' => 'string',
                    'enum' => ['flour', 'sugar', 'butter'],
                ],
                'servingSize' => [
                    'type' => 'integer',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'archived'],
                ],
            ],
            'required' => ['ingredient', 'servingSize', 'status'],
            'additionalProperties' => false,
        ], $tool->getParameters());
    }

    public function testMemoryFactoryExposesFlatDtoSchema()
    {
        $factory = new MemoryToolFactory($this->factoryWithProviders());
        $factory->addTool(ToolWithMappedArguments::class, 'tool_with_mapped_arguments', 'A tool that maps a flat payload onto a DTO');

        $tool = $this->firstTool($factory, ToolWithMappedArguments::class);

        $this->assertSame('ingredient', array_key_first($tool->getParameters()['properties']));
        $this->assertArrayHasKey('servingSize', $tool->getParameters()['properties']);
        $this->assertSame(['active', 'archived'], $tool->getParameters()['properties']['status']['enum']);
    }

    public function testResolverMapsFlatPayloadOntoDto()
    {
        $resolver = new ToolCallArgumentResolver();
        $metadata = new Tool(new ExecutionReference(ToolWithMappedArguments::class), 'tool_with_mapped_arguments', 'test');
        $toolCall = new ToolCall('1', 'tool_with_mapped_arguments', [
            'ingredient' => 'sugar',
            'servingSize' => 2,
            'status' => 'archived',
        ]);

        $arguments = $resolver->resolveArguments($metadata, $toolCall);

        $this->assertArrayHasKey('recipe', $arguments);
        $this->assertInstanceOf(MappedRecipe::class, $arguments['recipe']);
        $this->assertSame('sugar', $arguments['recipe']->ingredient);
        $this->assertSame(2, $arguments['recipe']->servingSize);
        $this->assertSame('archived', $arguments['recipe']->status);
    }

    public function testResolverAcceptsSerializedNameAliasesWhenConfiguredSerializerIsInjected()
    {
        $resolver = new ToolCallArgumentResolver($this->serializerWithSerializedNames());
        $metadata = new Tool(new ExecutionReference(ToolWithMappedArguments::class), 'tool_with_mapped_arguments', 'test');
        $toolCall = new ToolCall('1', 'tool_with_mapped_arguments', [
            'ingredient' => 'sugar',
            'serving_size' => 4,
        ]);

        $arguments = $resolver->resolveArguments($metadata, $toolCall);

        $this->assertSame(4, $arguments['recipe']->servingSize);
    }

    public function testResolverUsesDefaultValuesWhenOptionalFlatFieldsAreMissing()
    {
        $resolver = new ToolCallArgumentResolver();
        $metadata = new Tool(new ExecutionReference(ToolWithMappedArguments::class), 'tool_with_mapped_arguments', 'test');
        $toolCall = new ToolCall('1', 'tool_with_mapped_arguments', ['ingredient' => 'flour']);

        $arguments = $resolver->resolveArguments($metadata, $toolCall);

        $this->assertSame(1, $arguments['recipe']->servingSize);
        $this->assertSame('active', $arguments['recipe']->status);
    }

    public function testResolverFailsWhenRequiredFlatFieldIsMissing()
    {
        $resolver = new ToolCallArgumentResolver();
        $metadata = new Tool(new ExecutionReference(ToolWithMappedArguments::class), 'tool_with_mapped_arguments', 'test');
        $toolCall = new ToolCall('1', 'tool_with_mapped_arguments', ['servingSize' => 2]);

        $this->expectException(\Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException::class);
        $resolver->resolveArguments($metadata, $toolCall);
    }

    public function testResolverIgnoresUnknownFlatFields()
    {
        $resolver = new ToolCallArgumentResolver();
        $metadata = new Tool(new ExecutionReference(ToolWithMappedArguments::class), 'tool_with_mapped_arguments', 'test');
        $toolCall = new ToolCall('1', 'tool_with_mapped_arguments', [
            'ingredient' => 'butter',
            'unknown' => 'value',
        ]);

        $arguments = $resolver->resolveArguments($metadata, $toolCall);

        $this->assertSame('butter', $arguments['recipe']->ingredient);
    }

    public function testLegacyUnmappedDtoToolRemainsNested()
    {
        $tool = $this->firstTool(new ReflectionToolFactory(), ToolWithConstraints::class);

        $this->assertArrayHasKey('recipe', $tool->getParameters()['properties']);
        $this->assertArrayNotHasKey('ingredient', $tool->getParameters()['properties']);

        $resolver = new ToolCallArgumentResolver();
        $metadata = new Tool(new ExecutionReference(ToolWithConstraints::class), 'tool_with_constraints', 'test');
        $toolCall = new ToolCall('1', 'tool_with_constraints', ['recipe' => ['ingredient' => 'sugar']]);

        $arguments = $resolver->resolveArguments($metadata, $toolCall);

        $this->assertInstanceOf(Recipe::class, $arguments['recipe']);
        $this->assertSame('sugar', $arguments['recipe']->ingredient);
    }

    public function testLegacyScalarAndNoParamToolsRemainUnchanged()
    {
        $required = $this->firstTool(new ReflectionToolFactory(), ToolRequiredParams::class);
        $this->assertSame(['text', 'number'], array_keys($required->getParameters()['properties']));

        $noParams = $this->firstTool(new ReflectionToolFactory(), ToolNoParams::class);
        $this->assertNull($noParams->getParameters());

        $resolver = new ToolCallArgumentResolver();
        $metadata = new Tool(new ExecutionReference(ToolDate::class), 'tool_date', 'test');
        $toolCall = new ToolCall('1', 'tool_date', ['date' => '2025-06-29']);

        $this->assertEquals(['date' => new \DateTimeImmutable('2025-06-29')], $resolver->resolveArguments($metadata, $toolCall));
    }

    public function testValidationListenerStillValidatesMappedDto()
    {
        $resolver = new ToolCallArgumentResolver();
        $metadata = new Tool(new ExecutionReference(ToolWithMappedArguments::class), 'tool_with_mapped_arguments', 'test');
        $arguments = $resolver->resolveArguments($metadata, new ToolCall('1', 'tool_with_mapped_arguments', [
            'ingredient' => 'salt',
        ]));

        $listener = new ValidateToolCallArgumentsListener(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        $event = new ToolCallArgumentsResolved(new ToolWithMappedArguments(), $metadata, $arguments);

        $this->expectException(InvalidToolCallArgumentsException::class);
        $listener($event);
    }

    public function testToolboxDispatchesResolvedEventWithMappedDto()
    {
        $dispatcher = new EventDispatcher();
        $resolved = null;
        $dispatcher->addListener(ToolCallArgumentsResolved::class, static function (ToolCallArgumentsResolved $event) use (&$resolved) {
            $resolved = $event;
        });

        $toolbox = new Toolbox(
            [new ToolWithMappedArguments()],
            new ReflectionToolFactory($this->factoryWithProviders()),
            eventDispatcher: $dispatcher,
        );

        $result = $toolbox->execute(new ToolCall('1', 'tool_with_mapped_arguments', [
            'ingredient' => 'sugar',
            'servingSize' => 3,
        ]));

        $this->assertSame('Ingredient: sugar', $result->getResult());
        $this->assertInstanceOf(ToolCallArgumentsResolved::class, $resolved);
        $this->assertInstanceOf(MappedRecipe::class, $resolved->getArguments()['recipe']);
        $this->assertSame(3, $resolved->getArguments()['recipe']->servingSize);
    }

    public function testInvalidMappedSignaturesAreRejectedByFactories()
    {
        foreach ([
            [ToolWithInvalidMappedArguments::class, 'exactly one parameter'],
            [ToolWithNullableMappedArguments::class, 'must not be nullable'],
            [ToolWithScalarMappedArguments::class, 'concrete class'],
        ] as [$className, $message]) {
            try {
                iterator_to_array((new ReflectionToolFactory())->getTool($className));
                $this->fail(\sprintf('Expected ToolConfigurationException for %s.', $className));
            } catch (ToolConfigurationException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }

            try {
                (new MemoryToolFactory())->addTool($className, 'invalid_mapped_tool', 'invalid');
                $this->fail(\sprintf('Expected ToolConfigurationException for MemoryToolFactory and %s.', $className));
            } catch (ToolConfigurationException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    private function factoryWithProviders(): Factory
    {
        return new Factory(new Describer([
            new SerializerDescriber(),
            new TypeInfoDescriber(),
            new MethodDescriber(),
            new PropertyInfoDescriber(),
            new ValidatorConstraintsDescriber(),
            new SchemaAttributeDescriber([
                StatusProvider::class => new StatusProvider(),
            ]),
        ]));
    }

    private function serializerWithSerializedNames(): Serializer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $propertyTypeExtractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        return new Serializer([
            new DateTimeNormalizer(),
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata($classMetadataFactory),
                nameConverter: new MetadataAwareNameConverter($classMetadataFactory),
                propertyTypeExtractor: $propertyTypeExtractor,
            ),
            new ArrayDenormalizer(),
        ]);
    }

    private function firstTool(ReflectionToolFactory|MemoryToolFactory $factory, string $className): Tool
    {
        $tools = iterator_to_array($factory->getTool($className));

        return $tools[0];
    }
}
