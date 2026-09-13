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
use Mcp\Capability\Registry;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Schema\Icon;
use Mcp\Server;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Protocol;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use Mcp\Server\Stateless\RequestStateCodec;
use Mcp\Server\Stateless\StatelessProtocol;
use Mcp\Server\Subscription\InMemoryNotificationBus;
use Mcp\Server\Subscription\NotificationBusInterface;
use Mcp\Server\Subscription\Psr16NotificationBus;
use Mcp\Server\Subscription\PublishingEventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;

class McpBundleTest extends TestCase
{
    public function testNoServersRegistersNothing()
    {
        $container = $this->buildContainer([]);

        $this->assertFalse($container->hasDefinition('mcp.server.default'));
        $this->assertFalse($container->hasDefinition('mcp.server.route_loader'));
        $this->assertFalse($container->hasDefinition('mcp.data_collector'));
        $this->assertFalse($container->hasParameter('mcp.servers.elements'));
    }

    public function testServerIdentityDefaults()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        $calls = $this->callsNamed($container, 'setServerInfo');
        $this->assertSame(['default', '0.0.1', null, null, null], $calls[0][1]);
        $this->assertSame([50], $this->callsNamed($container, 'setPaginationLimit')[0][1]);
        $this->assertSame([null], $this->callsNamed($container, 'setInstructions')[0][1]);
    }

    public function testServerIdentityCustom()
    {
        $container = $this->buildContainer($this->config(['default' => [
            'name' => 'my-mcp-app',
            'version' => '1.2.3',
            'description' => 'My MCP Application',
            'icons' => [
                ['src' => 'https://example.com/icon.png', 'mime_type' => 'image/png', 'sizes' => ['64x64', '128x128']],
            ],
            'website_url' => 'https://example.com/mcp',
            'pagination_limit' => 25,
            'instructions' => 'This server provides weather and calendar tools',
        ]]));

        $arguments = $this->callsNamed($container, 'setServerInfo')[0][1];

        $this->assertSame('my-mcp-app', $arguments[0]);
        $this->assertSame('1.2.3', $arguments[1]);
        $this->assertSame('My MCP Application', $arguments[2]);
        $this->assertSame('https://example.com/mcp', $arguments[4]);

        // Icons must reach the SDK as Icon objects: the raw config array would be serialized
        // with its snake_case "mime_type" key instead of the protocol's "mimeType".
        $this->assertCount(1, $arguments[3]);
        $this->assertInstanceOf(Definition::class, $arguments[3][0]);
        $this->assertSame(Icon::class, $arguments[3][0]->getClass());
        $this->assertSame(['https://example.com/icon.png', 'image/png', ['64x64', '128x128']], $arguments[3][0]->getArguments());

        $this->assertSame([25], $this->callsNamed($container, 'setPaginationLimit')[0][1]);
        $this->assertSame(['This server provides weather and calendar tools'], $this->callsNamed($container, 'setInstructions')[0][1]);
    }

    public function testIconsDefaultToNullInsteadOfAnEmptyList()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        $this->assertNull($this->callsNamed($container, 'setServerInfo')[0][1][3]);
    }

    /**
     * @param array<string, bool> $transports
     * @param array<string, bool> $expectedServices
     */
    #[DataProvider('provideTransportsConfiguration')]
    public function testTransportsConfiguration(array $transports, array $expectedServices)
    {
        $container = $this->buildContainer($this->config(['default' => ['transports' => $transports]]));

        foreach ($expectedServices as $serviceId => $shouldExist) {
            $this->assertSame($shouldExist, $container->hasDefinition($serviceId), \sprintf('Service "%s"', $serviceId));
        }
    }

    public static function provideTransportsConfiguration(): iterable
    {
        yield 'no transports enabled' => [
            'transports' => ['stdio' => false, 'http' => false],
            'expectedServices' => [
                'mcp.server.command' => false,
                'mcp.server.default.controller' => false,
                'mcp.server.route_loader' => true,
                'mcp.debug_command' => true,
            ],
        ];

        yield 'stdio transport enabled' => [
            'transports' => ['stdio' => true, 'http' => false],
            'expectedServices' => [
                'mcp.server.command' => true,
                'mcp.server.default.controller' => false,
                'mcp.debug_command' => true,
            ],
        ];

        yield 'http transport enabled' => [
            'transports' => ['stdio' => false, 'http' => true],
            'expectedServices' => [
                'mcp.server.command' => false,
                'mcp.server.default.controller' => true,
                'mcp.server.default.middleware_factory' => true,
            ],
        ];

        yield 'both transports enabled' => [
            'transports' => ['stdio' => true, 'http' => true],
            'expectedServices' => [
                'mcp.server.command' => true,
                'mcp.server.default.controller' => true,
            ],
        ];
    }

    public function testMultipleServersGetTheirOwnServiceGraph()
    {
        $container = $this->buildContainer($this->config([
            'public' => ['http' => ['path' => '/mcp'], 'registry' => ['tools' => ['App\\Mcp\\Public\\']]],
            'editors' => ['http' => ['path' => '/mcp/editors'], 'registry' => ['tools' => ['*']]],
        ]));

        foreach (['public', 'editors'] as $name) {
            $this->assertTrue($container->hasDefinition('mcp.server.'.$name));
            $this->assertTrue($container->hasDefinition('mcp.server.'.$name.'.builder'));
            $this->assertTrue($container->hasDefinition('mcp.server.'.$name.'.registry'));
            $this->assertTrue($container->hasDefinition('mcp.server.'.$name.'.session.store'));
            $this->assertTrue($container->hasDefinition('mcp.server.'.$name.'.controller'));
        }

        // A shared registry would merge both servers' elements: the builder loads into it eagerly.
        $this->assertNotSame(
            $container->getDefinition('mcp.server.public.builder')->getMethodCalls(),
            $container->getDefinition('mcp.server.editors.builder')->getMethodCalls(),
        );

        $this->assertSame([
            'public' => ['tools' => ['App\\Mcp\\Public\\'], 'prompts' => [], 'resources' => [], 'resource_templates' => [], 'apps' => []],
            'editors' => ['tools' => ['*'], 'prompts' => [], 'resources' => [], 'resource_templates' => [], 'apps' => []],
        ], $container->getParameter('mcp.servers.elements'));
    }

    public function testElementPatternsAreNormalized()
    {
        $container = $this->buildContainer($this->config(['default' => ['registry' => ['tools' => ['\\App\\Mcp\\SearchTool']]]]));

        $this->assertSame(['App\\Mcp\\SearchTool'], $container->getParameter('mcp.servers.elements')['default']['tools']);
    }

    public function testServerWithoutARegistryIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "registry" under "mcp.servers.default" must be configured');

        $this->buildContainer(['mcp' => ['servers' => ['default' => []]]]);
    }

    public function testServerWithAnEmptyRegistryIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('An MCP server must expose at least one of "tools", "prompts", "resources", "resource_templates" or "apps".');

        $this->buildContainer(['mcp' => ['servers' => ['default' => ['registry' => []]]]]);
    }

    public function testRegistryAcceptsOneListForEveryKind()
    {
        $container = $this->buildContainer(['mcp' => ['servers' => ['default' => ['registry' => ['App\\Mcp\\']]]]]);

        $this->assertSame([
            'tools' => ['App\\Mcp\\'],
            'prompts' => ['App\\Mcp\\'],
            'resources' => ['App\\Mcp\\'],
            'resource_templates' => ['App\\Mcp\\'],
            'apps' => ['App\\Mcp\\'],
        ], $container->getParameter('mcp.servers.elements')['default']);
    }

    public function testRegistryAcceptsASingleStringForEveryKind()
    {
        $container = $this->buildContainer(['mcp' => ['servers' => ['default' => ['registry' => '*']]]]);

        $this->assertSame(array_fill_keys(
            ['tools', 'prompts', 'resources', 'resource_templates', 'apps'],
            ['*'],
        ), $container->getParameter('mcp.servers.elements')['default']);
    }

    public function testRegistryNarrowsEachKindSeparately()
    {
        $container = $this->buildContainer(['mcp' => ['servers' => ['default' => ['registry' => [
            'tools' => ['App\\Mcp\\Tool\\'],
            'apps' => ['App\\Apps\\'],
        ]]]]]);

        $this->assertSame([
            'tools' => ['App\\Mcp\\Tool\\'],
            'prompts' => [],
            'resources' => [],
            'resource_templates' => [],
            'apps' => ['App\\Apps\\'],
        ], $container->getParameter('mcp.servers.elements')['default']);
    }

    public function testWildcardCannotBeCombinedWithExplicitEntries()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "*" wildcard cannot be combined with explicit entries');

        $this->buildContainer($this->config(['default' => ['registry' => ['tools' => ['*', 'App\\Mcp\\SearchTool']]]]));
    }

    public function testInvalidServerNameIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('MCP server names must only contain letters, digits, underscores and hyphens');

        $this->buildContainer($this->config(['my.server' => []]));
    }

    public function testCollidingHttpPathsAreRejected()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('are both configured on the HTTP path "/mcp"');

        $this->buildContainer($this->config([
            'one' => ['http' => ['path' => '/mcp']],
            'two' => ['http' => ['path' => '/mcp']],
        ]));
    }

    public function testSharedSessionStorageIsRejected()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('share the same session storage');

        $this->buildContainer($this->config([
            'public' => ['http' => ['path' => '/mcp'], 'session' => ['store' => 'file', 'directory' => '/var/cache/mcp']],
            'editors' => ['http' => ['path' => '/mcp/editors'], 'session' => ['store' => 'file', 'directory' => '/var/cache/mcp']],
        ]));
    }

    public function testDataCollectorTagIncludesId()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        $definition = $container->getDefinition('mcp.data_collector');
        $this->assertTrue($definition->hasTag('data_collector'));
        $this->assertSame([['id' => 'mcp']], $definition->getTag('data_collector'));
    }

    public function testBuilderIsConfiguredWithTheSharedTaggedIterators()
    {
        $container = $this->buildContainer($this->config(['default' => ['transports' => ['stdio' => true, 'http' => true]]]));

        $this->assertCount(1, $this->callsNamed($container, 'setEventDispatcher'));

        foreach (['addRequestHandlers' => 'mcp.request_handler', 'addNotificationHandlers' => 'mcp.notification_handler', 'addLoaders' => 'mcp.loader'] as $method => $tag) {
            $argument = $this->callsNamed($container, $method)[0][1][0];
            $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
            $this->assertSame($tag, $argument->getTag());
        }
    }

    public function testRegistryAndSessionStoreAreBoundToTheServer()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        $this->assertSame('mcp.server.default.registry', (string) $this->callsNamed($container, 'setRegistry')[0][1][0]);
        $this->assertSame('mcp.server.default.session.store', (string) $this->callsNamed($container, 'setSession')[0][1][0]);
    }

    public function testRouteLoaderCarriesOneEntryPerHttpServer()
    {
        $container = $this->buildContainer($this->config([
            'public' => ['http' => ['path' => '/mcp']],
            'editors' => [],
            'cli' => ['transports' => ['stdio' => true, 'http' => false]],
        ]));

        $this->assertSame([
            ['name' => 'public', 'path' => '/mcp', 'controller' => 'mcp.server.public.controller::handle'],
            // No explicit path: derived from the server name, never a bare "/mcp".
            ['name' => 'editors', 'path' => '/mcp/editors', 'controller' => 'mcp.server.editors.controller::handle'],
        ], $container->getDefinition('mcp.server.route_loader')->getArgument(0));
    }

    public function testStdioCommandOnlyKnowsStdioServers()
    {
        $container = $this->buildContainer($this->config([
            'public' => ['http' => ['path' => '/mcp']],
            'cli' => ['transports' => ['stdio' => true, 'http' => false]],
        ]));

        $locatorId = (string) $container->getDefinition('mcp.server.command')->getArgument(0);
        $this->assertSame(['cli'], array_keys($container->getDefinition($locatorId)->getArgument(0)));
    }

    public function testStdioCommandNotRegisteredWithoutStdioServer()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        $this->assertFalse($container->hasDefinition('mcp.server.command'));
    }

    public function testServerIsAliasedForArgument()
    {
        $container = $this->buildContainer($this->config(['editors' => []]));

        $this->assertTrue($container->hasAlias(Server::class.' $editorsServer'));
        $this->assertSame('mcp.server.editors', (string) $container->getAlias(Server::class.' $editorsServer'));
    }

    public function testDnsRebindingProtectionDefaults()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        // No allowed hosts configured: keep the SDK default (localhost only).
        $this->assertNull($container->getDefinition('mcp.server.default.middleware_factory')->getArgument(0));
    }

    public function testDnsRebindingProtectionWithAllowedHosts()
    {
        $container = $this->buildContainer($this->config(['default' => ['http' => ['allowed_hosts' => ['example.com', 'mcp.example.com']]]]));

        $this->assertSame(['example.com', 'mcp.example.com'], $container->getDefinition('mcp.server.default.middleware_factory')->getArgument(0));
    }

    public function testDnsRebindingProtectionDisabled()
    {
        $container = $this->buildContainer($this->config(['default' => ['http' => ['allowed_hosts' => false]]]));

        $this->assertFalse($container->getDefinition('mcp.server.default.middleware_factory')->getArgument(0));
    }

    public function testSessionStoreFileDefaultsAreScopedToTheServer()
    {
        $container = $this->buildContainer($this->config(['editors' => []]));

        $definition = $container->getDefinition('mcp.server.editors.session.store');
        $this->assertSame(FileSessionStore::class, $definition->getClass());
        $this->assertSame('%kernel.cache_dir%/mcp-sessions/editors', $definition->getArgument(0));
        $this->assertSame(3600, $definition->getArgument(1));
    }

    public function testSessionStoreFileCustom()
    {
        $container = $this->buildContainer($this->config(['default' => ['session' => ['store' => 'file', 'directory' => '/var/cache/mcp', 'ttl' => 1800]]]));

        $definition = $container->getDefinition('mcp.server.default.session.store');
        $this->assertSame(FileSessionStore::class, $definition->getClass());
        $this->assertSame('/var/cache/mcp', $definition->getArgument(0));
        $this->assertSame(1800, $definition->getArgument(1));
    }

    public function testSessionStoreMemory()
    {
        $container = $this->buildContainer($this->config(['default' => ['session' => ['store' => 'memory', 'ttl' => 7200]]]));

        $definition = $container->getDefinition('mcp.server.default.session.store');
        $this->assertSame(InMemorySessionStore::class, $definition->getClass());
        $this->assertSame(7200, $definition->getArgument(0));
    }

    public function testSessionStoreCacheDefaultsAreScopedToTheServer()
    {
        $container = $this->buildContainer($this->config(['editors' => ['session' => ['store' => 'cache']]]));

        $definition = $container->getDefinition('mcp.server.editors.session.store');
        $this->assertSame(Psr16SessionStore::class, $definition->getClass());
        $this->assertSame('cache.mcp.sessions', (string) $definition->getArgument(0));
        $this->assertSame('mcp-editors-', $definition->getArgument(1));
        $this->assertSame(3600, $definition->getArgument(2));

        // The default pool is auto-created as a PSR-16 wrapper around cache.app.
        $cachePool = $container->getDefinition('cache.mcp.sessions');
        $this->assertSame(Psr16Cache::class, $cachePool->getClass());
        $this->assertSame('cache.app', (string) $cachePool->getArgument(0));
    }

    public function testSessionStoreCacheCustom()
    {
        $container = $this->buildContainer($this->config(['default' => ['session' => [
            'store' => 'cache',
            'cache_pool' => 'app.custom_cache',
            'prefix' => 'session-',
            'ttl' => 7200,
        ]]]));

        $definition = $container->getDefinition('mcp.server.default.session.store');
        $this->assertSame(Psr16SessionStore::class, $definition->getClass());
        $this->assertSame('app.custom_cache', (string) $definition->getArgument(0));
        $this->assertSame('session-', $definition->getArgument(1));
        $this->assertSame(7200, $definition->getArgument(2));

        $this->assertFalse($container->hasDefinition('cache.mcp.sessions'));
    }

    public function testSessionStoreFramework()
    {
        $container = $this->buildContainer($this->config(['default' => ['session' => ['store' => 'framework', 'prefix' => 'mcp-', 'ttl' => 1800]]]));

        $definition = $container->getDefinition('mcp.server.default.session.store');
        $this->assertSame(FrameworkSessionStore::class, $definition->getClass());
        $this->assertSame('session.handler', (string) $definition->getArgument(0));
        $this->assertSame('mcp-', $definition->getArgument(1));
        $this->assertSame(1800, $definition->getArgument(2));
    }

    public function testNoDiscoveryMethodCallOnBuilder()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        foreach ($container->getDefinition('mcp.server.default.builder')->getMethodCalls() as $call) {
            $this->assertNotSame('setDiscovery', $call[0], 'ServerBuilder must not use file-based discovery');
        }
    }

    public function testMcpAttributeAutoconfiguration()
    {
        $container = $this->buildContainer([]);

        $autoconfigurators = $container->getAttributeAutoconfigurators();

        foreach ([McpTool::class, McpPrompt::class, McpResource::class, McpResourceTemplate::class, AsMcpApp::class] as $attributeClass) {
            $this->assertArrayHasKey($attributeClass, $autoconfigurators);
        }
    }

    public function testMcpAttributeAutoconfigurationTagsMethod()
    {
        $container = $this->buildContainer([]);

        $configurator = $container->getAttributeAutoconfigurators()[McpTool::class][0];

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
        $this->assertTrue($autoconfigured[LoaderInterface::class]->hasTag('mcp.loader'));
    }

    public function testRequestHandlerInterfaceAutoconfiguration()
    {
        $container = $this->buildContainer([]);
        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(RequestHandlerInterface::class, $autoconfigured);
        $this->assertTrue($autoconfigured[RequestHandlerInterface::class]->hasTag('mcp.request_handler'));
    }

    public function testNotificationHandlerInterfaceAutoconfiguration()
    {
        $container = $this->buildContainer([]);
        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(NotificationHandlerInterface::class, $autoconfigured);
        $this->assertTrue($autoconfigured[NotificationHandlerInterface::class]->hasTag('mcp.notification_handler'));
    }

    public function testAppRendererRegisteredWhenTwigAvailable()
    {
        $container = $this->buildContainer([]);

        // Twig is a dev dependency of the bundle, so the renderer must be registered.
        $this->assertTrue(class_exists(\Twig\Environment::class));
        $this->assertTrue($container->hasDefinition(McpAppRenderer::SERVICE_ID));
    }

    public function testBothErasAreServedByOneServerOnOneEndpoint()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        // The SDK hands the transport a dispatcher per era, and the transport routes
        // each request; there is no second server, controller or route to configure.
        $definition = $container->getDefinition('mcp.server.default');
        $this->assertSame(Server::class, $definition->getClass());
        $this->assertSame('mcp.server.default.builder', (string) $definition->getFactory()[0]);
        $this->assertSame('build', $definition->getFactory()[1]);
        $this->assertSame(McpController::class, $container->getDefinition('mcp.server.default.controller')->getClass());
        $this->assertSame(
            [['name' => 'default', 'path' => '/mcp/default', 'controller' => 'mcp.server.default.controller::handle']],
            $container->getDefinition('mcp.server.route_loader')->getArgument(0),
        );

        // Which legs are live is the SDK's answer to the builder calls rather than something
        // the definition shows, so the built server is what settles it: a handshake-era
        // protocol (hence the session) and a modern-era one, on the same object.
        $server = $this->buildServer($container, 'default');
        $this->assertInstanceOf(Protocol::class, $this->legOf($server, 'protocol'));
        $this->assertInstanceOf(StatelessProtocol::class, $this->legOf($server, 'statelessProtocol'));
    }

    public function testTheSdkSupportIsInheritedWhenNoRevisionIsListed()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        foreach (['setModernVersions', 'withoutModernEra', 'setProtocolVersion'] as $call) {
            $this->assertSame([], $this->callsNamed($container, $call), $call);
        }
    }

    public function testListingModernRevisionsNarrowsTheModernLeg()
    {
        $container = $this->buildContainer($this->config([
            'modern' => ['protocol_versions' => ['2026-07-28']],
        ]));

        $versions = $this->callsNamed($container, 'setModernVersions', 'modern')[0][1][0];
        $this->assertCount(1, $versions);
        $this->assertSame(['2026-07-28'], $versions[0]->getArguments());

        // Nothing is pinned: the handshake keeps negotiating over every revision it knows.
        $this->assertSame([], $this->callsNamed($container, 'setProtocolVersion', 'modern'));
    }

    public function testListingOnlyHandshakeRevisionsPinsItAndRefusesTheModernEra()
    {
        $container = $this->buildContainer($this->config([
            'legacy' => ['protocol_versions' => ['2025-11-25']],
        ]));

        $pinned = $this->callsNamed($container, 'setProtocolVersion', 'legacy')[0][1][0];
        $this->assertSame(['2025-11-25'], $pinned->getArguments());

        $this->assertSame([[]], array_column($this->callsNamed($container, 'withoutModernEra', 'legacy'), 1));
        $this->assertSame([], $this->callsNamed($container, 'setModernVersions', 'legacy'));

        // The contrast that gives testBothErasAreServedByOneServerOnOneEndpoint its meaning:
        // the built server then carries no modern-era dispatcher at all.
        $this->assertNull($this->legOf($this->buildServer($container, 'legacy'), 'statelessProtocol'));
    }

    public function testBothErasCanBeNarrowedAtOnce()
    {
        $container = $this->buildContainer($this->config([
            'both' => ['protocol_versions' => ['2025-11-25', '2026-07-28']],
        ]));

        $this->assertSame(['2025-11-25'], $this->callsNamed($container, 'setProtocolVersion', 'both')[0][1][0]->getArguments());
        $this->assertSame(['2026-07-28'], $this->callsNamed($container, 'setModernVersions', 'both')[0][1][0][0]->getArguments());
    }

    public function testNarrowingTheHandshakeEraToASubsetIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/pinned to a single revision/');

        $this->buildContainer($this->config([
            'legacy' => ['protocol_versions' => ['2025-06-18', '2025-11-25']],
        ]));
    }

    public function testRequestStateIsConfiguredForMultiRoundTripAnswers()
    {
        // What bin2hex(random_bytes(32)) gives you. Asserted against the SDK's own minimum so the
        // fixture cannot drift back into a key the codec would refuse at runtime.
        $key = '4f8a1c05b7e2d93610fa8c4b2e57d0913a6ecb4728f105d3c9b60a7e8412fd56';
        $this->assertGreaterThanOrEqual(RequestStateCodec::MINIMUM_KEY_BYTES, \strlen($key));

        $container = $this->buildContainer($this->config([
            'modern' => ['request_state' => ['key' => $key, 'ttl' => 90]],
        ]));

        $this->assertSame([[$key, 90]], array_column($this->callsNamed($container, 'setRequestState', 'modern'), 1));
    }

    public function testAShortRequestStateKeyIsRejectedWhenTheContainerIsCompiled()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/must be at least 32 bytes/');

        $this->buildContainer($this->config(['modern' => ['request_state' => ['key' => 'too-short']]]));
    }

    public function testAnEnvPlaceholderKeyIsNotMeasured()
    {
        // Its length is unknowable at compile time, so the check must not turn a valid config
        // into an error. Here the reference is still written as it was configured; the test below
        // covers the other shape it takes, once a kernel's env machinery has registered it.
        $container = $this->buildContainer($this->config([
            'modern' => ['request_state' => ['key' => '%env(MCP_REQUEST_STATE_KEY)%']],
        ]));

        $this->assertSame(
            [['%env(MCP_REQUEST_STATE_KEY)%', 600]],
            array_column($this->callsNamed($container, 'setRequestState', 'modern'), 1),
        );
    }

    public function testAnEnvPlaceholderKeySurvivesTheRealCompilationPath()
    {
        // The test above builds the container by hand, so the config component never learns it is
        // handling a placeholder and the node's validator is simply reached and skipped. A kernel
        // runs ValidateEnvPlaceholdersPass, which re-processes the configuration with the
        // placeholder registered — and there cannotBeEmpty() on a validated node refuses every env
        // variable outright, before the validator gets a say.
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setParameter('kernel.project_dir', '/path/to/project');

        $container->registerExtension((new McpBundle())->getContainerExtension());
        $container->prependExtensionConfig('mcp', $this->config([
            'modern' => ['request_state' => ['key' => '%env(MCP_REQUEST_STATE_KEY)%']],
        ])['mcp']);

        (new MergeExtensionConfigurationPass(['mcp']))->process($container);
        (new ValidateEnvPlaceholdersPass())->process($container);

        // The placeholder has been swapped for the unique id the parameter bag resolves at
        // runtime, so what is asserted is that the key arrived at all and still names the variable.
        $calls = array_column($this->callsNamed($container, 'setRequestState', 'modern'), 1);
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('MCP_REQUEST_STATE_KEY', $calls[0][0]);
        $this->assertSame(600, $calls[0][1]);
    }

    public function testCacheHintsAreBuiltAsAPolicy()
    {
        $container = $this->buildContainer($this->config([
            'modern' => [
                'cache' => ['ttl_ms' => 30000, 'methods' => ['tools/list' => ['ttl_ms' => 3600000, 'scope' => 'public']]],
            ],
        ]));

        $calls = $this->callsNamed($container, 'setCachePolicy', 'modern');
        $this->assertCount(1, $calls);

        // The override wraps the default, so the outermost call is withMethod().
        $policy = $calls[0][1][0];
        $this->assertSame('withMethod', $policy->getFactory()[1]);
        $this->assertSame('tools/list', $policy->getArgument(0));
        $this->assertSame(3600000, $policy->getArgument(1));
    }

    public function testSubscriptionsAreOptOut()
    {
        $container = $this->buildContainer($this->config(['modern' => []]));

        $this->assertSame([], $this->callsNamed($container, 'setNotificationBus', 'modern'));
    }

    public function testSubscriptionsAreWiredWhenAskedFor()
    {
        $container = $this->buildContainer($this->config([
            'modern' => [
                'subscriptions' => ['bus' => 'memory', 'lifetime' => 5.0],
            ],
        ]));

        $this->assertSame([5.0], $this->callsNamed($container, 'setSubscriptionLifetime', 'modern')[0][1]);
        $this->assertSame(InMemoryNotificationBus::class, $container->getDefinition('mcp.server.modern.notification_bus')->getClass());
    }

    public function testTheBusIsAutowirableSoTheApplicationCanPublishToIt()
    {
        $container = $this->buildContainer($this->config([
            'modern' => ['subscriptions' => ['bus' => 'memory']],
        ]));

        // Registry changes publish themselves; everything else is the application calling
        // publish(), which it cannot do without a way to inject the bus.
        $this->assertSame(
            'mcp.server.modern.notification_bus',
            (string) $container->getAlias(NotificationBusInterface::class.' $modernNotificationBus'),
        );
    }

    public function testTheCacheBusNamespacesItsKeysPerServer()
    {
        $container = $this->buildContainer($this->config([
            'first' => ['subscriptions' => ['bus' => 'cache']],
            'second' => ['subscriptions' => ['bus' => 'cache']],
        ]));

        $arguments = [];
        foreach (['first', 'second'] as $name) {
            $definition = $container->getDefinition(\sprintf('mcp.server.%s.notification_bus', $name));
            $this->assertSame(Psr16NotificationBus::class, $definition->getClass());
            $arguments[$name] = $definition->getArguments();

            $this->assertSame('cache.mcp.notifications', (string) $arguments[$name]['$cache']);
            $this->assertSame('logger', (string) $arguments[$name]['$logger']);
        }

        // Both servers sit on the same default pool, so only the key prefix keeps one server's
        // notifications — and its cursor — out of the other's listen streams.
        $this->assertSame('mcp-first-notifications.', $arguments['first']['$prefix']);
        $this->assertSame('mcp-second-notifications.', $arguments['second']['$prefix']);
    }

    public function testTheDefaultNotificationPoolIsCreatedButACustomOneIsNot()
    {
        $container = $this->buildContainer($this->config([
            'default' => ['subscriptions' => ['bus' => 'cache']],
        ]));

        $pool = $container->getDefinition('cache.mcp.notifications');
        $this->assertSame(Psr16Cache::class, $pool->getClass());
        $this->assertSame('cache.app', (string) $pool->getArgument(0));

        $container = $this->buildContainer($this->config([
            'default' => ['subscriptions' => ['bus' => 'cache', 'cache_pool' => 'app.my_psr16']],
        ]));

        // An application-provided pool is referenced as configured, never invented: a typo has
        // to surface as an unknown service rather than as a silently working cache.
        $this->assertSame('app.my_psr16', (string) $container->getDefinition('mcp.server.default.notification_bus')->getArgument('$cache'));
        $this->assertFalse($container->hasDefinition('app.my_psr16'));
    }

    public function testTheRegistryPublishesItsChangesToTheConfiguredBus()
    {
        $container = $this->buildContainer($this->config([
            'modern' => ['subscriptions' => ['bus' => 'memory']],
        ]));

        $dispatcher = $container->getDefinition('mcp.server.modern.registry')->getArgument(0);

        $this->assertInstanceOf(Definition::class, $dispatcher);
        $this->assertSame(PublishingEventDispatcher::class, $dispatcher->getClass());
        $this->assertSame('event_dispatcher', (string) $dispatcher->getArgument(1));
    }

    public function testTheRegistryPublishesToTheSameBusTheProtocolReads()
    {
        $container = $this->buildContainer($this->config([
            'modern' => ['subscriptions' => ['bus' => 'memory']],
        ]));

        $published = (string) $container->getDefinition('mcp.server.modern.registry')->getArgument(0)->getArgument(0);
        $read = $this->callsNamed($container, 'setNotificationBus', 'modern');

        $this->assertCount(1, $read);
        $this->assertSame('mcp.server.modern.notification_bus', $published);
        $this->assertSame($published, (string) $read[0][1][0]);
    }

    public function testEachServerPublishesToItsOwnBus()
    {
        $container = $this->buildContainer($this->config([
            'first' => ['subscriptions' => ['bus' => 'memory']],
            'second' => ['subscriptions' => ['bus' => 'memory']],
        ]));

        $this->assertNotSame(
            (string) $container->getDefinition('mcp.server.first.registry')->getArgument(0)->getArgument(0),
            (string) $container->getDefinition('mcp.server.second.registry')->getArgument(0)->getArgument(0),
        );
    }

    public function testTheRegistryUsesThePlainDispatcherWithoutSubscriptions()
    {
        $container = $this->buildContainer($this->config(['modern' => []]));

        $this->assertSame('event_dispatcher', (string) $container->getDefinition('mcp.server.modern.registry')->getArgument(0));
    }

    public function testNoBusIsRegisteredWhenSubscriptionsAreOff()
    {
        $container = $this->buildContainer($this->config(['default' => []]));

        $this->assertFalse($container->hasDefinition('mcp.server.default.notification_bus'));
        $this->assertSame([], $this->callsNamed($container, 'setNotificationBus'));
        $this->assertSame([], $this->callsNamed($container, 'setSubscriptionLifetime'));
    }

    /**
     * Builds an "mcp" configuration from partial server definitions, defaulting each server to
     * exposing every tool so the "at least one element list" validation is satisfied.
     *
     * @param array<string, array<string, mixed>> $servers
     *
     * @return array<string, mixed>
     */
    private function config(array $servers): array
    {
        foreach ($servers as $name => $server) {
            $servers[$name] += ['registry' => '*'];
        }

        return ['mcp' => ['servers' => $servers]];
    }

    /**
     * @return list<array{0: string, 1: array<int, mixed>}>
     */
    private function callsNamed(ContainerBuilder $container, string $method, string $server = 'default'): array
    {
        return array_values(array_filter(
            $container->getDefinition('mcp.server.'.$server.'.builder')->getMethodCalls(),
            static fn (array $call): bool => $call[0] === $method,
        ));
    }

    /**
     * Instantiates the SDK server the container describes, so an assertion can reach what the
     * builder calls actually produced rather than restating the calls themselves.
     *
     * References are stood in for and tagged iterators come out empty: which eras a server
     * carries does not depend on the registered elements.
     */
    private function buildServer(ContainerBuilder $container, string $name): Server
    {
        $dispatcher = new EventDispatcher();
        $services = [
            'event_dispatcher' => $dispatcher,
            'logger' => new NullLogger(),
            \sprintf('mcp.server.%s.registry', $name) => new Registry($dispatcher),
            \sprintf('mcp.server.%s.session.store', $name) => new InMemorySessionStore(),
        ];

        $builder = Server::builder();
        foreach ($container->getDefinition(\sprintf('mcp.server.%s.builder', $name))->getMethodCalls() as $call) {
            \call_user_func_array([$builder, $call[0]], $this->resolveArguments($call[1], $services));
        }

        return $builder->build();
    }

    /**
     * @param array<array-key, mixed> $arguments
     * @param array<string, object>   $services
     *
     * @return array<array-key, mixed>
     */
    private function resolveArguments(array $arguments, array $services): array
    {
        return array_map(fn (mixed $argument): mixed => $this->resolveArgument($argument, $services), $arguments);
    }

    /**
     * @param array<string, object> $services
     */
    private function resolveArgument(mixed $argument, array $services): mixed
    {
        if ($argument instanceof Reference) {
            return $services[(string) $argument];
        }

        if ($argument instanceof TaggedIteratorArgument) {
            return [];
        }

        if (\is_array($argument)) {
            return $this->resolveArguments($argument, $services);
        }

        if (!$argument instanceof Definition) {
            return $argument;
        }

        $arguments = $this->resolveArguments($argument->getArguments(), $services);
        $factory = $argument->getFactory();

        if (null === $factory) {
            $class = $argument->getClass();
            $this->assertNotNull($class);

            return new $class(...$arguments);
        }

        $this->assertIsCallable($factory);

        return \call_user_func_array($factory, $arguments);
    }

    /**
     * The eras are private state of the SDK's server, and deliberately so: nothing but a test
     * has business asking which dispatchers a built server carries.
     */
    private function legOf(Server $server, string $property): mixed
    {
        return (new \ReflectionProperty(Server::class, $property))->getValue($server);
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
