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

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class McpBundleTest extends TestCase
{
    public function testDefaultConfiguration()
    {
        $container = $this->buildContainer([]);

        $this->assertSame('app', $container->getParameter('mcp.app'));
        $this->assertSame('0.0.1', $container->getParameter('mcp.version'));
        $this->assertNull($container->getParameter('mcp.description'));
        $this->assertSame([], $container->getParameter('mcp.icons'));
        $this->assertNull($container->getParameter('mcp.website_url'));
        $this->assertSame(50, $container->getParameter('mcp.pagination_limit'));
        $this->assertNull($container->getParameter('mcp.instructions'));
    }

    public function testDataCollectorTagIncludesId()
    {
        $container = $this->buildContainer([]);
        $definition = $container->getDefinition('mcp.data_collector');
        $this->assertTrue($definition->hasTag('data_collector'));
        $this->assertSame([['id' => 'mcp']], $definition->getTag('data_collector'));
    }

    public function testCustomConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'app' => 'my-mcp-app',
                'version' => '1.2.3',
                'description' => 'My MCP Application',
                'icons' => [
                    [
                        'src' => 'https://example.com/icon.png',
                        'mime_type' => 'image/png',
                        'sizes' => ['64x64', '128x128'],
                    ],
                ],
                'website_url' => 'https://example.com/mcp',
                'pagination_limit' => 25,
                'instructions' => 'This server provides weather and calendar tools',
            ],
        ]);

        $this->assertSame('my-mcp-app', $container->getParameter('mcp.app'));
        $this->assertSame('1.2.3', $container->getParameter('mcp.version'));
        $this->assertSame('My MCP Application', $container->getParameter('mcp.description'));
        $this->assertSame([
            [
                'src' => 'https://example.com/icon.png',
                'mime_type' => 'image/png',
                'sizes' => ['64x64', '128x128'],
            ],
        ], $container->getParameter('mcp.icons'));
        $this->assertSame('https://example.com/mcp', $container->getParameter('mcp.website_url'));
        $this->assertSame(25, $container->getParameter('mcp.pagination_limit'));
        $this->assertSame('This server provides weather and calendar tools', $container->getParameter('mcp.instructions'));
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, bool>  $expectedServices
     */
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
                'mcp.server.debug_command' => false,
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
                'mcp.server.debug_command' => true,
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
                'mcp.server.debug_command' => true,
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
                'mcp.server.debug_command' => true,
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
        $this->assertArrayHasKey(McpTool::class, $attributeAutoconfigurators);
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
        $this->assertArrayHasKey(McpPrompt::class, $attributeAutoconfigurators);
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
        $this->assertArrayHasKey(McpResource::class, $attributeAutoconfigurators);
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
        $this->assertArrayHasKey(McpResourceTemplate::class, $attributeAutoconfigurators);
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
        $this->assertSame(FileSessionStore::class, $sessionStoreDefinition->getClass());
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
        $this->assertSame(InMemorySessionStore::class, $sessionStoreDefinition->getClass());
        $sessionArguments = $sessionStoreDefinition->getArguments();
        $this->assertSame(7200, $sessionArguments[0]); // Custom TTL for memory store
    }

    public function testDnsRebindingProtectionDefaults()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
            ],
        ]);

        $factoryDefinition = $container->getDefinition('mcp.middleware_factory');
        $arguments = $factoryDefinition->getArguments();
        $this->assertNull($arguments[0]); // No allowed hosts configured: keep the SDK default (localhost only)
    }

    public function testDnsRebindingProtectionWithAllowedHosts()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
                'http' => [
                    'allowed_hosts' => ['example.com', 'mcp.example.com'],
                ],
            ],
        ]);

        $factoryDefinition = $container->getDefinition('mcp.middleware_factory');
        $arguments = $factoryDefinition->getArguments();
        $this->assertSame(['example.com', 'mcp.example.com'], $arguments[0]);
    }

    public function testDnsRebindingProtectionDisabled()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
                'http' => [
                    'allowed_hosts' => false,
                ],
            ],
        ]);

        $factoryDefinition = $container->getDefinition('mcp.middleware_factory');
        $arguments = $factoryDefinition->getArguments();
        $this->assertFalse($arguments[0]);
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
        $this->assertSame(FileSessionStore::class, $sessionStoreDefinition->getClass());
        $arguments = $sessionStoreDefinition->getArguments();
        $this->assertSame('/var/cache/mcp', $arguments[0]); // Custom directory
        $this->assertSame(1800, $arguments[1]); // Custom TTL
    }

    public function testSessionStoreCacheConfigurationDefault()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
                'http' => [
                    'session' => [
                        'store' => 'cache',
                    ],
                ],
            ],
        ]);

        // Verify session store is configured with Psr16SessionStore
        $sessionStoreDefinition = $container->getDefinition('mcp.session.store');
        $this->assertSame(Psr16SessionStore::class, $sessionStoreDefinition->getClass());
        $arguments = $sessionStoreDefinition->getArguments();

        // Check arguments
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('cache.mcp.sessions', (string) $arguments[0]); // Default cache pool
        $this->assertSame('mcp-', $arguments[1]); // Default prefix
        $this->assertSame(3600, $arguments[2]); // Default TTL

        // Verify default cache pool was created as PSR-16 wrapper
        $this->assertTrue($container->hasDefinition('cache.mcp.sessions'));
        $cachePoolDefinition = $container->getDefinition('cache.mcp.sessions');
        $this->assertSame(Psr16Cache::class, $cachePoolDefinition->getClass());
        $cachePoolArgs = $cachePoolDefinition->getArguments();
        $this->assertInstanceOf(Reference::class, $cachePoolArgs[0]);
        $this->assertSame('cache.app', (string) $cachePoolArgs[0]);
    }

    public function testSessionStoreCacheConfigurationCustom()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
                'http' => [
                    'session' => [
                        'store' => 'cache',
                        'cache_pool' => 'app.custom_cache',
                        'prefix' => 'session-',
                        'ttl' => 7200,
                    ],
                ],
            ],
        ]);

        $sessionStoreDefinition = $container->getDefinition('mcp.session.store');
        $this->assertSame(Psr16SessionStore::class, $sessionStoreDefinition->getClass());
        $arguments = $sessionStoreDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('app.custom_cache', (string) $arguments[0]); // Custom cache pool
        $this->assertSame('session-', $arguments[1]); // Custom prefix
        $this->assertSame(7200, $arguments[2]); // Custom TTL

        // No default cache pool definition should be created for custom cache pool
        $this->assertFalse($container->hasDefinition('cache.mcp.sessions'));
    }

    public function testSessionStoreFrameworkConfiguration()
    {
        $container = $this->buildContainer([
            'mcp' => [
                'client_transports' => [
                    'http' => true,
                ],
                'http' => [
                    'session' => [
                        'store' => 'framework',
                        'prefix' => 'mcp-',
                        'ttl' => 1800,
                    ],
                ],
            ],
        ]);

        $sessionStoreDefinition = $container->getDefinition('mcp.session.store');
        $this->assertSame(FrameworkSessionStore::class, $sessionStoreDefinition->getClass());
        $arguments = $sessionStoreDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('session.handler', (string) $arguments[0]);
        $this->assertSame('mcp-', $arguments[1]);
        $this->assertSame(1800, $arguments[2]);
    }

    public function testNoDiscoveryMethodCallOnBuilder()
    {
        $container = $this->buildContainer([]);

        foreach ($container->getDefinition('mcp.server.builder')->getMethodCalls() as $call) {
            $this->assertNotSame('setDiscovery', $call[0], 'ServerBuilder must not use file-based discovery');
        }
    }

    public function testMcpAttributeAutoconfigurationTagsMethod()
    {
        $container = $this->buildContainer([]);

        $attributes = [
            McpTool::class => 'mcp.tool',
            McpPrompt::class => 'mcp.prompt',
            McpResource::class => 'mcp.resource',
            McpResourceTemplate::class => 'mcp.resource_template',
        ];

        $autoconfigurators = $container->getAttributeAutoconfigurators();

        foreach ($attributes as $attributeClass => $tag) {
            $this->assertArrayHasKey($attributeClass, $autoconfigurators);
        }

        $configurator = $autoconfigurators[McpTool::class][0];

        $definition = new ChildDefinition('abstract');
        $configurator($definition, new McpTool(), new \ReflectionMethod(InvokableService::class, '__invoke'));
        $this->assertSame([['method' => '__invoke']], $definition->getTag('mcp.tool'));

        $definition = new ChildDefinition('abstract');
        $configurator($definition, new McpTool(), new \ReflectionClass(InvokableService::class));
        $this->assertSame([['method' => '__invoke']], $definition->getTag('mcp.tool'));
    }

    public function testMcpAttributeAutoconfigurationRejectsNonInvokableClass()
    {
        $container = $this->buildContainer([]);

        $configurator = $container->getAttributeAutoconfigurators()[McpTool::class][0];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The class "%s" uses #[%s] as a class-level attribute but has no "__invoke()" method.', NonInvokableService::class, McpTool::class));

        $configurator(new ChildDefinition('abstract'), new McpTool(), new \ReflectionClass(NonInvokableService::class));
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

    public function testAppsDefaultConfiguration()
    {
        $container = $this->buildContainer([]);

        $this->assertNull($container->getParameter('mcp.apps.enabled'));
    }

    public function testAppsEnabledFlag()
    {
        $container = $this->buildContainer(['mcp' => ['apps' => ['enabled' => true]]]);

        $this->assertTrue($container->getParameter('mcp.apps.enabled'));
    }

    public function testAsMcpAppAttributeAutoconfiguration()
    {
        $container = $this->buildContainer([]);

        $attributeAutoconfigurators = $container->getAttributeAutoconfigurators();
        $this->assertArrayHasKey(AsMcpApp::class, $attributeAutoconfigurators);
    }

    public function testAppRendererRegisteredWhenTwigAvailable()
    {
        $container = $this->buildContainer([]);

        // Twig is a dev dependency of the bundle, so the renderer must be registered.
        $this->assertTrue(class_exists(\Twig\Environment::class));
        $this->assertTrue($container->hasDefinition(McpAppRenderer::SERVICE_ID));
    }

    /**
     * @param array<string, mixed> $configuration
     */
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

class InvokableService
{
    public function __invoke(): string
    {
        return 'ok';
    }
}

class NonInvokableService
{
    public function doSomething(): string
    {
        return 'ok';
    }
}
