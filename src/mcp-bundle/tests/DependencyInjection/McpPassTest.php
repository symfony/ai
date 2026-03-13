<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\DependencyInjection;

use Mcp\Capability\Registry\Loader\ArrayLoader;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Tests\Fixtures\TestPromptService;
use Symfony\AI\McpBundle\Tests\Fixtures\TestResourceService;
use Symfony\AI\McpBundle\Tests\Fixtures\TestResourceTemplateService;
use Symfony\AI\McpBundle\Tests\Fixtures\TestToolService;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpPassTest extends TestCase
{
    public function testCreatesServiceLocatorForAllMcpServices()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        $container->setDefinition('tool_service', (new Definition(TestToolService::class))->addTag('mcp.tool'));
        $container->setDefinition('prompt_service', (new Definition(TestPromptService::class))->addTag('mcp.prompt'));
        $container->setDefinition('resource_service', (new Definition(TestResourceService::class))->addTag('mcp.resource'));
        $container->setDefinition('template_service', (new Definition(TestResourceTemplateService::class))->addTag('mcp.resource_template'));

        $pass = new McpPass();
        $pass->process($container);

        $builderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $builderDefinition->getMethodCalls();

        $this->assertCount(1, $methodCalls);
        $this->assertSame('setContainer', $methodCalls[0][0]);

        // Verify service locator contains all MCP services
        $serviceLocatorId = (string) $methodCalls[0][1][0];
        $this->assertTrue($container->hasDefinition($serviceLocatorId));

        $serviceLocatorDef = $container->getDefinition($serviceLocatorId);
        $services = $serviceLocatorDef->getArgument(0);

        $this->assertArrayHasKey(TestToolService::class, $services);
        $this->assertArrayHasKey(TestPromptService::class, $services);
        $this->assertArrayHasKey(TestResourceService::class, $services);
        $this->assertArrayHasKey(TestResourceTemplateService::class, $services);

        // Verify services are ServiceClosureArguments wrapping References
        $this->assertInstanceOf(ServiceClosureArgument::class, $services[TestToolService::class]);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services[TestPromptService::class]);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services[TestResourceService::class]);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services[TestResourceTemplateService::class]);

        // Verify the underlying values are References
        $this->assertInstanceOf(Reference::class, $services[TestToolService::class]->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services[TestPromptService::class]->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services[TestResourceService::class]->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services[TestResourceTemplateService::class]->getValues()[0]);
    }

    public function testDoesNothingWhenNoMcpServicesTagged()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        $pass = new McpPass();
        $pass->process($container);

        $builderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $builderDefinition->getMethodCalls();

        $this->assertSame([], $methodCalls);
    }

    public function testDoesNothingWhenNoServerBuilder()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('tool_service', (new Definition(TestToolService::class))->addTag('mcp.tool'));

        $pass = new McpPass();
        $pass->process($container);

        // Should not create any service locator
        $serviceIds = array_keys($container->getDefinitions());
        $serviceLocators = array_filter($serviceIds, static fn ($id) => str_contains($id, 'service_locator'));

        $this->assertSame([], $serviceLocators);
    }

    public function testHandlesPartialMcpServices()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        // Only add tools and prompts, no resources
        $container->setDefinition('tool_service', (new Definition(TestToolService::class))->addTag('mcp.tool'));
        $container->setDefinition('prompt_service', (new Definition(TestPromptService::class))->addTag('mcp.prompt'));

        $pass = new McpPass();
        $pass->process($container);

        $builderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $builderDefinition->getMethodCalls();

        $this->assertCount(1, $methodCalls);
        $this->assertSame('setContainer', $methodCalls[0][0]);

        // Verify service locator contains only the tagged services
        $serviceLocatorId = (string) $methodCalls[0][1][0];
        $serviceLocatorDef = $container->getDefinition($serviceLocatorId);
        $services = $serviceLocatorDef->getArgument(0);

        $this->assertArrayHasKey(TestToolService::class, $services);
        $this->assertArrayHasKey(TestPromptService::class, $services);
        $this->assertArrayNotHasKey(TestResourceService::class, $services);
        $this->assertArrayNotHasKey(TestResourceTemplateService::class, $services);

        // Verify services are ServiceClosureArguments wrapping References
        $this->assertInstanceOf(ServiceClosureArgument::class, $services[TestToolService::class]);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services[TestPromptService::class]);

        // Verify the underlying values are References
        $this->assertInstanceOf(Reference::class, $services[TestToolService::class]->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services[TestPromptService::class]->getValues()[0]);
    }

    public function testCreatesArrayLoaderDefinitionWithAllTypes()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        $container->setDefinition('tool_service', (new Definition(TestToolService::class))->addTag('mcp.tool'));
        $container->setDefinition('prompt_service', (new Definition(TestPromptService::class))->addTag('mcp.prompt'));
        $container->setDefinition('resource_service', (new Definition(TestResourceService::class))->addTag('mcp.resource'));
        $container->setDefinition('template_service', (new Definition(TestResourceTemplateService::class))->addTag('mcp.resource_template'));

        $pass = new McpPass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition('mcp.container_loader'));

        $loaderDefinition = $container->getDefinition('mcp.container_loader');
        $this->assertSame(ArrayLoader::class, $loaderDefinition->getClass());
        $this->assertTrue($loaderDefinition->hasTag('mcp.loader'));

        $arguments = $loaderDefinition->getArguments();
        $this->assertCount(4, $arguments);

        // Argument 0: tools
        $tools = $arguments[0];
        $this->assertCount(1, $tools);
        $this->assertSame('test_tool', $tools[0]['name']);
        $this->assertSame('A test tool', $tools[0]['description']);
        $this->assertSame([TestToolService::class, '__invoke'], $tools[0]['handler']);

        // Argument 1: resources
        $resources = $arguments[1];
        $this->assertCount(1, $resources);
        $this->assertSame('test_resource', $resources[0]['name']);
        $this->assertSame('test://resource', $resources[0]['uri']);
        $this->assertSame([TestResourceService::class, '__invoke'], $resources[0]['handler']);

        // Argument 2: resource templates
        $resourceTemplates = $arguments[2];
        $this->assertCount(1, $resourceTemplates);
        $this->assertSame('test_template', $resourceTemplates[0]['name']);
        $this->assertSame('test://resource/{id}', $resourceTemplates[0]['uriTemplate']);
        $this->assertSame([TestResourceTemplateService::class, '__invoke'], $resourceTemplates[0]['handler']);

        // Argument 3: prompts
        $prompts = $arguments[3];
        $this->assertCount(1, $prompts);
        $this->assertSame('test_prompt', $prompts[0]['name']);
        $this->assertSame('A test prompt', $prompts[0]['description']);
        $this->assertSame([TestPromptService::class, '__invoke'], $prompts[0]['handler']);
    }

    public function testArrayLoaderDefinitionWithPartialTypes()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        $container->setDefinition('tool_service', (new Definition(TestToolService::class))->addTag('mcp.tool'));

        $pass = new McpPass();
        $pass->process($container);

        $this->assertTrue($container->hasDefinition('mcp.container_loader'));

        $loaderDefinition = $container->getDefinition('mcp.container_loader');
        $arguments = $loaderDefinition->getArguments();

        // Tools should have one entry
        $this->assertCount(1, $arguments[0]);

        // Resources, resource templates, and prompts should be empty
        $this->assertSame([], $arguments[1]);
        $this->assertSame([], $arguments[2]);
        $this->assertSame([], $arguments[3]);
    }

    public function testArrayLoaderNotCreatedWhenNoServices()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        $pass = new McpPass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition('mcp.container_loader'));
    }

    public function testToolEntryContainsInputSchema()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());
        $container->setDefinition('tool_service', (new Definition(TestToolService::class))->addTag('mcp.tool'));

        $pass = new McpPass();
        $pass->process($container);

        $loaderDefinition = $container->getDefinition('mcp.container_loader');
        $tools = $loaderDefinition->getArguments()[0];

        $this->assertArrayHasKey('inputSchema', $tools[0]);
        $this->assertIsArray($tools[0]['inputSchema']);
    }

    public function testPromptEntryContainsArguments()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());
        $container->setDefinition('prompt_service', (new Definition(TestPromptService::class))->addTag('mcp.prompt'));

        $pass = new McpPass();
        $pass->process($container);

        $loaderDefinition = $container->getDefinition('mcp.container_loader');
        $prompts = $loaderDefinition->getArguments()[3];

        $this->assertArrayHasKey('arguments', $prompts[0]);
        $this->assertCount(1, $prompts[0]['arguments']);
        $this->assertSame('topic', $prompts[0]['arguments'][0]['name']);
        $this->assertTrue($prompts[0]['arguments'][0]['required']);
    }
}
