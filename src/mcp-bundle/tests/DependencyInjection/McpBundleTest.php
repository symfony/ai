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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(McpBundle::class)]
class McpBundleTest extends TestCase
{
    public function testDefaultConfiguration()
    {
        $container = $this->buildContainer([]);

        $this->assertSame('app', $container->getParameter('mcp.app'));
        $this->assertSame('0.0.1', $container->getParameter('mcp.version'));
        $this->assertSame(50, $container->getParameter('mcp.pagination_limit'));
        $this->assertNull($container->getParameter('mcp.instructions'));
    }

    public function testCustomConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'app' => 'my-mcp-app',
                'version' => '1.2.3',
                'pagination_limit' => 25,
                'instructions' => 'This server provides weather and calendar tools',
            ],
        ]);

        $this->assertSame('my-mcp-app', $container->getParameter('mcp.app'));
        $this->assertSame('1.2.3', $container->getParameter('mcp.version'));
        $this->assertSame(25, $container->getParameter('mcp.pagination_limit'));
        $this->assertSame('This server provides weather and calendar tools', $container->getParameter('mcp.instructions'));
    }

    public function testMcpLoggerServiceIsCreated()
    {
        $container = $this->buildContainer([]);

        $this->assertTrue($container->hasDefinition('monolog.logger.mcp'));

        $definition = $container->getDefinition('monolog.logger.mcp');
        $this->assertSame('monolog.logger_prototype', $definition->getParent());
        $this->assertSame(['mcp'], $definition->getArguments());
        $this->assertTrue($definition->hasTag('monolog.logger'));
    }

    #[DataProvider('provideClientTransportsConfiguration')]
    public function testClientTransportsConfiguration(array $config, array $expectedServices)
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => $config,
            ],
        ]);

        foreach ($expectedServices as $serviceId => $shouldExist) {
            if ($shouldExist) {
                $this->assertTrue($container->hasDefinition($serviceId), \sprintf('Service "%s" should exist', $serviceId));
            } else {
                $this->assertFalse($container->hasDefinition($serviceId), \sprintf('Service "%s" should not exist', $serviceId));
            }
        }
    }

    public static function provideClientTransportsConfiguration(): iterable
    {
        yield 'no transports enabled' => [
            'config' => [
                'stdio' => false,
                'sse' => false,
            ],
            'expectedServices' => [
                'mcp.server.command' => false,
                'mcp.server.controller' => false,
                'mcp.server.route_loader' => false,
            ],
        ];

        yield 'stdio transport enabled' => [
            'config' => [
                'stdio' => true,
                'sse' => false,
            ],
            'expectedServices' => [
                'mcp.server.command' => true,
                'mcp.server.controller' => false,
                'mcp.server.route_loader' => true,
            ],
        ];

        yield 'sse transport enabled' => [
            'config' => [
                'stdio' => false,
                'sse' => true,
            ],
            'expectedServices' => [
                'mcp.server.command' => false,
                'mcp.server.controller' => true,
                'mcp.server.route_loader' => true,
            ],
        ];

        yield 'both transports enabled' => [
            'config' => [
                'stdio' => true,
                'sse' => true,
            ],
            'expectedServices' => [
                'mcp.server.command' => true,
                'mcp.server.controller' => true,
                'mcp.server.route_loader' => true,
            ],
        ];
    }

    public function testServerServices()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'stdio' => true,
                    'sse' => true,
                ],
            ],
        ]);

        // Test that core MCP services are registered
        $this->assertTrue($container->hasDefinition('mcp.server'));
        $this->assertTrue($container->hasDefinition('mcp.server.sse.store.cache_pool'));
    }

    public function testMcpToolAttributeAutoconfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'stdio' => true,
                ],
            ],
        ]);

        // Test that McpTool attribute is autoconfigured with mcp.tool tag
        $attributeAutoconfigurators = $container->getAttributeAutoconfigurators();
        $this->assertArrayHasKey('Mcp\Capability\Attribute\McpTool', $attributeAutoconfigurators);
    }

    public function testMcpPromptAttributeAutoconfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'stdio' => true,
                ],
            ],
        ]);

        // Test that McpPrompt attribute is autoconfigured with mcp.prompt tag
        $attributeAutoconfigurators = $container->getAttributeAutoconfigurators();
        $this->assertArrayHasKey('Mcp\Capability\Attribute\McpPrompt', $attributeAutoconfigurators);
    }

    public function testMcpResourceAttributeAutoconfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'stdio' => true,
                ],
            ],
        ]);

        // Test that McpResource attribute is autoconfigured with mcp.resource tag
        $attributeAutoconfigurators = $container->getAttributeAutoconfigurators();
        $this->assertArrayHasKey('Mcp\Capability\Attribute\McpResource', $attributeAutoconfigurators);
    }

    public function testMcpResourceTemplateAttributeAutoconfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'stdio' => true,
                ],
            ],
        ]);

        // Test that McpResourceTemplate attribute is autoconfigured with mcp.resource_template tag
        $attributeAutoconfigurators = $container->getAttributeAutoconfigurators();
        $this->assertArrayHasKey('Mcp\Capability\Attribute\McpResourceTemplate', $attributeAutoconfigurators);
    }

    private function buildContainer(array $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setParameter('kernel.project_dir', '/path/to/project');

        $extension = (new McpBundle())->getContainerExtension();
        $extension->load($configuration, $container);

        return $container;
    }
}
