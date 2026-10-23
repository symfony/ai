<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\AiBundle\Tests\Fixture\JsonSchema\CategoryProvider;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithMcpSchema;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class McpSchemaWiringTest extends TestCase
{
    public function testMcpConstraintsReachAgentToolSchema()
    {
        $container = $this->buildContainer();
        $this->assertArrayHasKey('ai.platform.json_schema.describer', $container->getDefinition('ai.platform.json_schema.describer.mcp_schema')->getTags());
        $this->assertArrayNotHasKey('ai.platform.json_schema.describer', $container->getDefinition('ai.platform.json_schema.describer.schema_attribute')->getTags());
        $container->getDefinition('ai.tool_factory')->setPublic(true);
        $container->compile();
        $factory = $container->get('ai.tool_factory');
        $this->assertInstanceOf(ReflectionToolFactory::class, $factory);
        $tools = iterator_to_array($factory->getTool(ToolWithMcpSchema::class));
        $schema = $tools[0]->getParameters();

        $this->assertSame(['article', 'advertisement', 'obituary'], $schema['properties']['kind']['enum']);
        $this->assertSame('["string","null"]', json_encode($schema['properties']['kind']['type']));
        $this->assertSame(0, $schema['properties']['limit']['minimum']);
        $this->assertSame(200, $schema['properties']['limit']['maximum']);
        $this->assertSame(0, $schema['properties']['term']['minLength']);
        $this->assertSame('^[a-z]*$', $schema['properties']['term']['pattern']);
        $this->assertSame(['type' => 'string'], $schema['properties']['tags']['items']);
        $this->assertSame(0, $schema['properties']['tags']['minItems']);
        $this->assertFalse($schema['properties']['tags']['uniqueItems']);
        $this->assertSame('object', $schema['properties']['options']['type']);
        $this->assertSame([], $schema['properties']['options']['required']);
        $this->assertFalse($schema['properties']['options']['additionalProperties']);
    }

    public function testPlatformAttributeOverridesEntireMcpKeywords()
    {
        $factory = $this->schemaFactory();
        $schema = $factory->buildParameters(ToolWithMcpSchema::class, 'overridden');

        $this->assertSame(['second'], $schema['properties']['value']['enum']);
        $this->assertSame(10, $schema['properties']['value']['maxLength']);
    }

    public function testPlatformProviderOverridesMcpAttribute()
    {
        $container = $this->buildContainer();
        $container->register(CategoryProvider::class, CategoryProvider::class)->setAutoconfigured(true)->setArgument(0, ['runtime']);
        $container->getDefinition('ai.platform.json_schema_factory')->setPublic(true);
        $container->compile();
        $factory = $container->get('ai.platform.json_schema_factory');
        $this->assertInstanceOf(Factory::class, $factory);
        $schema = $factory->buildParameters(ToolWithMcpSchema::class, 'provided');

        $this->assertSame(['runtime'], $schema['properties']['value']['enum']);
    }

    public function testDefinitionIsMergedWithoutWrapper()
    {
        $schema = $this->schemaFactory()->buildParameters(ToolWithMcpSchema::class, 'defined');

        $this->assertSame(0, $schema['properties']['value']['minimum']);
        $this->assertSame('[0,1]', json_encode($schema['properties']['value']['enum']));
        $this->assertArrayNotHasKey('definition', $schema['properties']['value']);
    }

    public function testPlatformOnlyAttributeKeepsItsBehavior()
    {
        $schema = $this->schemaFactory()->buildParameters(ToolWithMcpSchema::class, 'platformOnly');

        $this->assertSame(['only'], $schema['properties']['value']['enum']);
    }

    public function testMethodSchemaDoesNotChangeParameterConstraints()
    {
        $schema = $this->schemaFactory()->buildParameters(ToolWithMcpSchema::class, 'methodOnly');

        $this->assertSame(['value'], array_keys($schema['properties']));
        $this->assertSame('string', $schema['properties']['value']['type']);
        $this->assertArrayNotHasKey('required', $schema['properties']['value']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSdkIsOptional()
    {
        foreach (spl_autoload_functions() as $autoload) {
            spl_autoload_unregister($autoload);
            spl_autoload_register(static function (string $class) use ($autoload): void {
                if (!str_starts_with($class, 'Mcp\\')) {
                    $autoload($class);
                }
            });
        }
        $this->assertFalse(class_exists('Mcp\\Capability\\Attribute\\Schema'));
        $container = $this->buildContainer();

        $this->assertFalse($container->hasDefinition('ai.platform.json_schema.describer.mcp_schema'));
        $this->assertArrayHasKey('ai.platform.json_schema.describer', $container->getDefinition('ai.platform.json_schema.describer.schema_attribute')->getTags());
        $container->getDefinition('ai.platform.json_schema_factory')->setPublic(true);
        $container->compile();
        $this->assertInstanceOf(Factory::class, $container->get('ai.platform.json_schema_factory'));
    }

    private function schemaFactory(): Factory
    {
        $container = $this->buildContainer();
        $container->getDefinition('ai.platform.json_schema_factory')->setPublic(true);
        $container->compile();
        $factory = $container->get('ai.platform.json_schema_factory');
        $this->assertInstanceOf(Factory::class, $factory);

        return $factory;
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));
        $container->register('property_info.reflection_extractor', ReflectionExtractor::class);
        $container->register('property_info', PropertyInfoExtractor::class)
            ->setArguments([[new \Symfony\Component\DependencyInjection\Reference('property_info.reflection_extractor')], [new \Symfony\Component\DependencyInjection\Reference('property_info.reflection_extractor')]]);
        $container->register('serializer.mapping.class_metadata_factory', \Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory::class)->setArgument(0, new Definition(\Symfony\Component\Serializer\Mapping\Loader\AttributeLoader::class));
        $bundle = new AiBundle();
        $bundle->build($container);
        $bundle->getContainerExtension()->load(['ai' => []], $container);

        return $container;
    }
}
