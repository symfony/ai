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

use Mcp\Capability\Tool\IdentifierInterface;
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
    }

    public function testCustomConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'app' => 'my-mcp-app',
                'version' => '1.2.3',
            ],
        ]);

        $this->assertSame('my-mcp-app', $container->getParameter('mcp.app'));
        $this->assertSame('1.2.3', $container->getParameter('mcp.version'));
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

    public function testToolAutoconfiguration()
    {
        $container = $this->buildContainer([]);

        $autoconfiguredInstances = $container->getAutoconfiguredInstanceof();

        $this->assertArrayHasKey(IdentifierInterface::class, $autoconfiguredInstances);
        $this->assertArrayHasKey('mcp.tool', $autoconfiguredInstances[IdentifierInterface::class]->getTags());
    }

    public function testDefaultPageSizeConfiguration()
    {
        $container = $this->buildContainer([]);

        // Test that the default page_size parameter is set to 20
        $this->assertSame(20, $container->getParameter('mcp.page_size'));

        // Test that the main MCP server service is registered
        $this->assertTrue($container->hasDefinition('mcp.server'));
    }

    public function testCustomPageSizeConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'page_size' => 50,
            ],
        ]);

        // Test that the custom page_size parameter is set
        $this->assertSame(50, $container->getParameter('mcp.page_size'));
    }

    public function testCoreServicesRegistered()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'stdio' => true,
                    'sse' => false,
                ],
            ],
        ]);

        // Test that core MCP services are registered
        $this->assertTrue($container->hasDefinition('mcp.registry'));
        $this->assertTrue($container->hasDefinition('mcp.json_rpc_handler'));
        $this->assertTrue($container->hasDefinition('mcp.server'));
    }

    public function testDefaultServerCapabilitiesConfiguration()
    {
        $container = $this->buildContainer([]);

        // Test that default server capabilities are set
        $serverCapabilities = $container->getParameter('mcp.server_capabilities');
        $this->assertSame([
            'tools' => true,
            'tools_list_changed' => null,
            'resources' => null,
            'resources_subscribe' => false,
            'resources_list_changed' => null,
            'prompts' => null,
            'prompts_list_changed' => null,
            'logging' => false,
            'completions' => true,
            'experimental' => [],
        ], $serverCapabilities);
    }

    public function testCustomServerCapabilitiesConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'server_capabilities' => [
                    'tools' => false,
                    'logging' => true,
                    'completions' => false,
                    'experimental' => ['custom_feature' => true],
                ],
            ],
        ]);

        // Test that custom server capabilities are set
        $serverCapabilities = $container->getParameter('mcp.server_capabilities');
        $this->assertSame([
            'tools' => false,
            'logging' => true,
            'completions' => false,
            'experimental' => ['custom_feature' => true],
            'tools_list_changed' => null,
            'resources' => null,
            'resources_subscribe' => false,
            'resources_list_changed' => null,
            'prompts' => null,
            'prompts_list_changed' => null,
        ], $serverCapabilities);
    }

    private function buildContainer(array $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');

        $extension = (new McpBundle())->getContainerExtension();
        $extension->load($configuration, $container);

        return $container;
    }
}
