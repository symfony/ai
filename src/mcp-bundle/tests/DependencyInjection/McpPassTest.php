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

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
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

        // Add services with different MCP tags
        $container->setDefinition('tool_service', (new Definition())->addTag('mcp.tool'));
        $container->setDefinition('prompt_service', (new Definition())->addTag('mcp.prompt'));
        $container->setDefinition('resource_service', (new Definition())->addTag('mcp.resource'));
        $container->setDefinition('template_service', (new Definition())->addTag('mcp.resource_template'));

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

        $this->assertArrayHasKey('tool_service', $services);
        $this->assertArrayHasKey('prompt_service', $services);
        $this->assertArrayHasKey('resource_service', $services);
        $this->assertArrayHasKey('template_service', $services);

        // Verify services are ServiceClosureArguments wrapping References
        $this->assertInstanceOf(ServiceClosureArgument::class, $services['tool_service']);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services['prompt_service']);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services['resource_service']);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services['template_service']);

        // Verify the underlying values are References
        $this->assertInstanceOf(Reference::class, $services['tool_service']->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services['prompt_service']->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services['resource_service']->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services['template_service']->getValues()[0]);
    }

    public function testDoesNothingWhenNoMcpServicesTagged()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        $pass = new McpPass();
        $pass->process($container);

        $builderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $builderDefinition->getMethodCalls();

        $this->assertEmpty($methodCalls);
    }

    public function testDoesNothingWhenNoServerBuilder()
    {
        $container = new ContainerBuilder();

        // Add MCP services but no server builder
        $container->setDefinition('tool_service', (new Definition())->addTag('mcp.tool'));

        $pass = new McpPass();
        $pass->process($container);

        // Should not create any service locator
        $serviceIds = array_keys($container->getDefinitions());
        $serviceLocators = array_filter($serviceIds, fn ($id) => str_contains($id, 'service_locator'));

        $this->assertEmpty($serviceLocators);
    }

    public function testHandlesPartialMcpServices()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('mcp.server.builder', new Definition());

        // Only add tools and prompts, no resources
        $container->setDefinition('tool_service', (new Definition())->addTag('mcp.tool'));
        $container->setDefinition('prompt_service', (new Definition())->addTag('mcp.prompt'));

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

        $this->assertArrayHasKey('tool_service', $services);
        $this->assertArrayHasKey('prompt_service', $services);
        $this->assertArrayNotHasKey('resource_service', $services);
        $this->assertArrayNotHasKey('template_service', $services);

        // Verify services are ServiceClosureArguments wrapping References
        $this->assertInstanceOf(ServiceClosureArgument::class, $services['tool_service']);
        $this->assertInstanceOf(ServiceClosureArgument::class, $services['prompt_service']);

        // Verify the underlying values are References
        $this->assertInstanceOf(Reference::class, $services['tool_service']->getValues()[0]);
        $this->assertInstanceOf(Reference::class, $services['prompt_service']->getValues()[0]);
    }
}
