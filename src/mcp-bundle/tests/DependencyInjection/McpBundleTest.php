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

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class McpBundleTest extends TestCase
{
    public function testDefaultConfiguration()
    {
        $container = $this->buildContainer([]);

        $this->assertSame('app', $container->getParameter('mcp.app'));
        $this->assertSame('0.0.1', $container->getParameter('mcp.version'));
        $this->assertSame(50, $container->getParameter('mcp.pagination_limit'));
        $this->assertNull($container->getParameter('mcp.instructions'));
        $this->assertSame(['src'], $container->getParameter('mcp.discovery.scan_dirs'));
        $this->assertSame([], $container->getParameter('mcp.discovery.exclude_dirs'));
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
                'http' => false,
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
                'http' => false,
            ],
            'expectedServices' => [
                'mcp.server.command' => true,
                'mcp.server.controller' => false,
                'mcp.server.route_loader' => true,
            ],
        ];

        yield 'http transport enabled' => [
            'config' => [
                'stdio' => false,
                'http' => true,
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
                'http' => true,
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
                    'http' => true,
                ],
            ],
        ]);

        // Test that core MCP services are registered
        $this->assertTrue($container->hasDefinition('mcp.server'));
        $this->assertTrue($container->hasDefinition('mcp.session.store'));

        // Test that ServerBuilder is properly configured with EventDispatcher
        $builderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $builderDefinition->getMethodCalls();

        $hasEventDispatcherCall = false;
        $hasRequestHandlers = false;
        $hasNotificationHandlers = false;
        $hasLoaders = false;

        foreach ($methodCalls as $call) {
            if ('setEventDispatcher' === $call[0]) {
                $hasEventDispatcherCall = true;
            }

            if ('addRequestHandlers' === $call[0]) {
                $argument = $call[1][0];
                if (
                    $argument instanceof TaggedIteratorArgument
                    && 'mcp.request_handler' === $argument->getTag()
                ) {
                    $hasRequestHandlers = true;
                }
            }

            if ('addNotificationHandlers' === $call[0]) {
                $argument = $call[1][0];
                if (
                    $argument instanceof TaggedIteratorArgument
                    && 'mcp.notification_handler' === $argument->getTag()
                ) {
                    $hasNotificationHandlers = true;
                }
            }

            if ('addLoaders' === $call[0]) {
                $argument = $call[1][0];
                if (
                    $argument instanceof TaggedIteratorArgument
                    && 'mcp.loader' === $argument->getTag()
                ) {
                    $hasLoaders = true;
                }
            }
        }

        $this->assertTrue($hasEventDispatcherCall, 'ServerBuilder should have setEventDispatcher method call');
        $this->assertTrue($hasRequestHandlers, 'ServerBuilder should have addRequestHandlers with mcp.request_handler tag');
        $this->assertTrue($hasNotificationHandlers, 'ServerBuilder should have addNotificationHandlers with mcp.notification_handler tag');
        $this->assertTrue($hasLoaders, 'ServerBuilder should have addLoaders with mcp.loader tag');
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

    public function testHttpConfigurationDefaults()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
            ],
        ]);

        // Test HTTP route loader defaults
        $this->assertTrue($container->hasDefinition('mcp.server.route_loader'));
        $routeLoaderDefinition = $container->getDefinition('mcp.server.route_loader');
        $arguments = $routeLoaderDefinition->getArguments();
        $this->assertTrue($arguments[0]); // HTTP transport enabled
        $this->assertSame('/_mcp', $arguments[1]); // Default path

        // Test session store defaults (file store)
        $this->assertTrue($container->hasDefinition('mcp.session.store'));
        $sessionStoreDefinition = $container->getDefinition('mcp.session.store');
        $this->assertSame('Mcp\Server\Session\FileSessionStore', $sessionStoreDefinition->getClass());
        $sessionArguments = $sessionStoreDefinition->getArguments();
        $this->assertSame('%kernel.cache_dir%/mcp-sessions', $sessionArguments[0]); // Default directory
        $this->assertSame(3600, $sessionArguments[1]); // Default TTL
    }

    public function testHttpConfigurationCustom()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
                'http' => [
                    'path' => '/custom-mcp',
                    'session' => [
                        'store' => 'memory',
                        'directory' => '/custom/sessions',
                        'ttl' => 7200,
                    ],
                ],
            ],
        ]);

        // Test custom HTTP path
        $routeLoaderDefinition = $container->getDefinition('mcp.server.route_loader');
        $arguments = $routeLoaderDefinition->getArguments();
        $this->assertSame('/custom-mcp', $arguments[1]);

        // Test custom session store (memory)
        $sessionStoreDefinition = $container->getDefinition('mcp.session.store');
        $this->assertSame('Mcp\Server\Session\InMemorySessionStore', $sessionStoreDefinition->getClass());
        $sessionArguments = $sessionStoreDefinition->getArguments();
        $this->assertSame(7200, $sessionArguments[0]); // Custom TTL for memory store
    }

    public function testSessionStoreFileConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
                'http' => [
                    'session' => [
                        'store' => 'file',
                        'directory' => '/var/cache/mcp',
                        'ttl' => 1800,
                    ],
                ],
            ],
        ]);

        $sessionStoreDefinition = $container->getDefinition('mcp.session.store');
        $this->assertSame('Mcp\Server\Session\FileSessionStore', $sessionStoreDefinition->getClass());
        $arguments = $sessionStoreDefinition->getArguments();
        $this->assertSame('/var/cache/mcp', $arguments[0]); // Custom directory
        $this->assertSame(1800, $arguments[1]); // Custom TTL
    }

    public function testDiscoveryDefaultConfiguration()
    {
        $container = $this->buildContainer([]);

        $this->assertSame(['src'], $container->getParameter('mcp.discovery.scan_dirs'));
        $this->assertSame([], $container->getParameter('mcp.discovery.exclude_dirs'));

        // Verify the builder service uses the correct parameters
        $builderDefinition = $container->getDefinition('mcp.server.builder');
        $methodCalls = $builderDefinition->getMethodCalls();

        $setDiscoveryCall = null;
        foreach ($methodCalls as $call) {
            if ('setDiscovery' === $call[0]) {
                $setDiscoveryCall = $call;
                break;
            }
        }

        $this->assertNotNull($setDiscoveryCall, 'ServerBuilder should have setDiscovery method call');
    }

    public function testDiscoveryCustomConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'discovery' => [
                    'scan_dirs' => ['src', 'lib', 'modules'],
                    'exclude_dirs' => ['src/DataFixtures', 'tests'],
                ],
            ],
        ]);

        $this->assertSame(['src', 'lib', 'modules'], $container->getParameter('mcp.discovery.scan_dirs'));
        $this->assertSame(['src/DataFixtures', 'tests'], $container->getParameter('mcp.discovery.exclude_dirs'));
    }

    public function testDiscoveryWithExcludeDirsOnly()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'discovery' => [
                    'exclude_dirs' => ['src/DataFixtures'],
                ],
            ],
        ]);

        $this->assertSame(['src'], $container->getParameter('mcp.discovery.scan_dirs'));
        $this->assertSame(['src/DataFixtures'], $container->getParameter('mcp.discovery.exclude_dirs'));
    }

    public function testLoaderInterfaceAutoconfiguration()
    {
        $container = $this->buildContainer([]);
        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(LoaderInterface::class, $autoconfigured);
        $definition = $autoconfigured[LoaderInterface::class];
        $this->assertTrue($definition->hasTag('mcp.loader'));
    }

    public function testRequestHandlerInterfaceAutoconfiguration()
    {
        $container = $this->buildContainer([]);
        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(RequestHandlerInterface::class, $autoconfigured);
        $definition = $autoconfigured[RequestHandlerInterface::class];
        $this->assertTrue($definition->hasTag('mcp.request_handler'));
    }

    public function testNotificationHandlerInterfaceAutoconfiguration()
    {
        $container = $this->buildContainer([]);
        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(NotificationHandlerInterface::class, $autoconfigured);
        $definition = $autoconfigured[NotificationHandlerInterface::class];
        $this->assertTrue($definition->hasTag('mcp.notification_handler'));
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
