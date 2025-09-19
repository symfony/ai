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

use Mcp\Server\ServerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\DependencyInjection\McpToolPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(McpToolPass::class)]
class McpToolPassTest extends TestCase
{
    public function testCreatesServiceLocatorForTaggedServices()
    {
        $container = new ContainerBuilder();

        $container->register('mcp.server.builder', ServerBuilder::class);

        $container->register('tool1', 'MockTool1')
            ->addTag('mcp.tool');

        $container->register('tool2', 'MockTool2')
            ->addTag('mcp.tool');

        $container->register('not_a_tool', 'MockService');

        $pass = new McpToolPass();
        $pass->process($container);

        // Check that server builder gets a container
        $serverBuilderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $serverBuilderDefinition->getMethodCalls();
        $hasSetContainer = false;
        foreach ($methodCalls as $call) {
            if ('setContainer' === $call[0]) {
                $hasSetContainer = true;
                break;
            }
        }
        $this->assertTrue($hasSetContainer);
    }

    public function testDoesNothingWhenNoToolsTagged()
    {
        $container = new ContainerBuilder();

        $container->register('mcp.server.builder', ServerBuilder::class);
        $container->register('service1', 'MockService');
        $container->register('service2', 'MockService');

        $pass = new McpToolPass();
        $pass->process($container);

        // Check that server builder doesn't get a container when no tools
        $serverBuilderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $serverBuilderDefinition->getMethodCalls();
        $this->assertEmpty($methodCalls);
    }

    public function testDoesNothingWhenNoServerBuilder()
    {
        $container = new ContainerBuilder();

        $container->register('tool1', 'MockTool1')
            ->addTag('mcp.tool');

        $container->register('tool2', 'MockTool2')
            ->addTag('mcp.tool');

        $pass = new McpToolPass();
        $pass->process($container);

        // Check that no service locator is created when no server builder
        $definitions = $container->getDefinitions();
        $serviceLocatorCount = 0;
        foreach ($definitions as $definition) {
            if ('Symfony\\Component\\DependencyInjection\\ServiceLocator' === $definition->getClass()) {
                ++$serviceLocatorCount;
            }
        }
        $this->assertSame(0, $serviceLocatorCount);
    }
}
