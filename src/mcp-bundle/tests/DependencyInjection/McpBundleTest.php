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
use Mcp\Schema\Icon;
use Mcp\Server;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use Mcp\Server\Stateless\StatelessProtocol;
use Mcp\Server\Task\InMemoryTaskStore;
use Mcp\Server\Task\TasksExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Controller\StatelessMcpController;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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
                'mcp.server.debug_command' => true,
            ],
        ];

        yield 'stdio transport enabled' => [
            'transports' => ['stdio' => true, 'http' => false],
            'expectedServices' => [
                'mcp.server.command' => true,
                'mcp.server.default.controller' => false,
                'mcp.server.debug_command' => true,
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
            'public' => ['http' => ['path' => '/mcp'], 'tools' => ['App\\Mcp\\Public\\']],
            'editors' => ['http' => ['path' => '/mcp/editors'], 'tools' => ['*']],
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
        $container = $this->buildContainer($this->config(['default' => ['tools' => ['\\App\\Mcp\\SearchTool']]]));

        $this->assertSame(['App\\Mcp\\SearchTool'], $container->getParameter('mcp.servers.elements')['default']['tools']);
    }

    public function testServerWithoutAnyElementListIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('An MCP server must expose at least one of "tools", "prompts", "resources", "resource_templates" or "apps".');

        $this->buildContainer(['mcp' => ['servers' => ['default' => []]]]);
    }

    public function testWildcardCannotBeCombinedWithExplicitEntries()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "*" wildcard cannot be combined with explicit entries');

        $this->buildContainer($this->config(['default' => ['tools' => ['*', 'App\\Mcp\\SearchTool']]]));
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

    public function testTheHandshakeLifecycleIsTheDefault()
    {
        $container = $this->buildContainer($this->config(['default' => ['tools' => ['*']]]));

        $this->assertSame(Server::class, $container->getDefinition('mcp.server.default')->getClass());
        $this->assertSame(McpController::class, $container->getDefinition('mcp.server.default.controller')->getClass());
        $this->assertNotSame([], $this->callsNamed($container, 'setSession'));
    }

    public function testAStatelessServerIsBuiltAsADispatcherWithNoSession()
    {
        $container = $this->buildContainer($this->config([
            'modern' => ['lifecycle' => 'stateless', 'tools' => ['*']],
        ]));

        $server = $container->getDefinition('mcp.server.modern');
        $this->assertSame(StatelessProtocol::class, $server->getClass());
        $this->assertSame('buildStateless', $server->getFactory()[1]);

        $this->assertSame([], $this->callsNamed($container, 'setSession', 'modern'));
        $this->assertFalse($container->hasDefinition('mcp.server.modern.session.store'));

        $this->assertSame(
            StatelessMcpController::class,
            $container->getDefinition('mcp.server.modern.controller')->getClass(),
        );
    }

    public function testAStatelessServerAnswersForTheRevisionsItLists()
    {
        $container = $this->buildContainer($this->config([
            'modern' => ['lifecycle' => 'stateless', 'protocol_versions' => ['2026-07-28'], 'tools' => ['*']],
        ]));

        $versions = $container->getDefinition('mcp.server.modern')->getArgument(0);
        $this->assertCount(1, $versions);
        $this->assertSame(['2026-07-28'], $versions[0]->getArguments());
    }

    public function testStatelessRefusesStdio()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/cannot be served over STDIO/');

        $this->buildContainer($this->config([
            'modern' => ['lifecycle' => 'stateless', 'transports' => ['stdio' => true], 'tools' => ['*']],
        ]));
    }

    public function testRequestStateIsConfiguredForMultiRoundTripAnswers()
    {
        $container = $this->buildContainer($this->config([
            'modern' => ['lifecycle' => 'stateless', 'request_state' => ['key' => 'a-key', 'ttl' => 90], 'tools' => ['*']],
        ]));

        $this->assertSame([['a-key', 90]], array_column($this->callsNamed($container, 'setRequestState', 'modern'), 1));
    }

    public function testCacheHintsAreBuiltAsAPolicy()
    {
        $container = $this->buildContainer($this->config([
            'modern' => [
                'lifecycle' => 'stateless',
                'cache' => ['ttl_ms' => 30000, 'methods' => ['tools/list' => ['ttl_ms' => 3600000, 'scope' => 'public']]],
                'tools' => ['*'],
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

    public function testTasksAndSubscriptionsAreOptOut()
    {
        $container = $this->buildContainer($this->config(['modern' => ['lifecycle' => 'stateless', 'tools' => ['*']]]));

        $this->assertSame([], $this->callsNamed($container, 'enableExtension', 'modern'));
        $this->assertSame([], $this->callsNamed($container, 'setNotificationBus', 'modern'));
    }

    public function testTasksAndSubscriptionsAreWiredWhenAskedFor()
    {
        $container = $this->buildContainer($this->config([
            'modern' => [
                'lifecycle' => 'stateless',
                'tasks' => ['store' => 'memory'],
                'subscriptions' => ['bus' => 'memory', 'lifetime' => 5.0],
                'tools' => ['*'],
            ],
        ]));

        $extension = $this->callsNamed($container, 'enableExtension', 'modern')[0][1][0];
        $this->assertSame(TasksExtension::class, $extension->getClass());
        $this->assertSame(InMemoryTaskStore::class, $extension->getArgument(0)->getClass());

        $this->assertSame([5.0], $this->callsNamed($container, 'setSubscriptionLifetime', 'modern')[0][1]);
        $this->assertTrue($container->hasDefinition('mcp.server.modern.notification_bus'));
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
            $servers[$name] += ['tools' => ['*']];
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
