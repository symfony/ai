<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Registry;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Client as McpSdkClient;
use Mcp\Client\Builder as McpSdkClientBuilder;
use Mcp\Client\Handler\Notification\LoggingNotificationHandler;
use Mcp\Client\Handler\Request\ElicitationRequestHandler;
use Mcp\Client\Handler\Request\SamplingRequestHandler;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Client\Transport\StdioTransport;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Icon;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Client\McpClient;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\AI\McpBundle\Client\ServerConnection;
use Symfony\AI\McpBundle\Client\ServerLogForwarder;
use Symfony\AI\McpBundle\Client\TransportFactory;
use Symfony\AI\McpBundle\Command\ClientDebugCommand;
use Symfony\AI\McpBundle\Command\DebugCommand;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\DependencyInjection\McpAppPass;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Environment;

final class McpBundle extends AbstractBundle
{
    /**
     * The kinds of element a server can expose, mapped to the tag carrying them.
     */
    public const ELEMENT_KINDS = [
        'tools' => 'mcp.tool',
        'prompts' => 'mcp.prompt',
        'resources' => 'mcp.resource',
        'resource_templates' => 'mcp.resource_template',
        'apps' => 'mcp.app',
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/options.php');
    }

    /**
     * @param array{servers: array<string, array<string, mixed>>, clients: array<string, array<string, mixed>>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $this->registerMcpAttributes($builder);
        $this->configureApps($builder);

        $builder->registerForAutoconfiguration(LoaderInterface::class)
            ->addTag('mcp.loader');

        $builder->registerForAutoconfiguration(RequestHandlerInterface::class)
            ->addTag('mcp.request_handler');

        $builder->registerForAutoconfiguration(NotificationHandlerInterface::class)
            ->addTag('mcp.notification_handler');

        if ([] === $config['servers'] && [] === $config['clients']) {
            return;
        }

        $container->import('../config/services.php');

        if ([] !== $config['servers']) {
            $this->configureServers($config['servers'], $builder);
        }

        if ([] !== $config['clients']) {
            $this->configureClients($config['clients'], $builder);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        // McpAppPass runs before McpPass so the bound app-renderer handler services it creates are
        // included in the handler service locators McpPass builds.
        $container->addCompilerPass(new McpAppPass(), priority: 10);
        $container->addCompilerPass(new McpPass());
    }

    /**
     * @param array<string, array<string, mixed>> $clients
     */
    private function configureClients(array $clients, ContainerBuilder $container): void
    {
        $logger = new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE);
        $clientReferences = [];

        foreach ($clients as $clientName => $client) {
            $connectionReferences = [];

            foreach ($client['servers'] as $serverName => $server) {
                $id = \sprintf('mcp.client.%s.server.%s', $clientName, $serverName);

                $container->setDefinition($id.'.transport', $this->clientTransport($server, $logger, $container))
                    ->addTag('monolog.logger', ['channel' => 'mcp']);

                $container->setDefinition($id.'.builder', $this->clientBuilder($clientName, $serverName, $client, $server, $logger));

                $container->register($id.'.client', McpSdkClient::class)
                    ->setFactory([new Reference($id.'.builder'), 'build'])
                    ->addTag('monolog.logger', ['channel' => 'mcp']);

                $container->register($id, ServerConnection::class)
                    ->setArguments([$clientName, $serverName, new Reference($id.'.client'), new Reference($id.'.transport')])
                    ->addTag('mcp.client.connection', ['client' => $clientName, 'server' => $serverName])
                    // Stdio connections own a child process; a worker must not carry one across messages.
                    ->addTag('kernel.reset', ['method' => 'reset']);

                $connectionReferences[$serverName] = new Reference($id);
            }

            $locatorId = \sprintf('mcp.client.%s.locator', $clientName);
            $container->register($locatorId, ServiceLocator::class)
                ->setArguments([$connectionReferences])
                ->addTag('container.service_locator');

            $container->register('mcp.client.'.$clientName, McpClient::class)
                ->setArguments([$clientName, new Reference($locatorId)])
                ->addTag('mcp.client', ['name' => $clientName]);

            $container->registerAliasForArgument('mcp.client.'.$clientName, McpClientInterface::class, $clientName.' client');

            $clientReferences[$clientName] = new Reference('mcp.client.'.$clientName);
        }

        if (1 === \count($clients)) {
            $container->setAlias(McpClientInterface::class, 'mcp.client.'.array_key_first($clients));
        }

        $container->register('mcp.client.locator', ServiceLocator::class)
            ->setArguments([$clientReferences])
            ->addTag('container.service_locator');

        $container->register('mcp.client.debug_command', ClientDebugCommand::class)
            ->setArguments([new Reference('mcp.client.locator')])
            ->addTag('console.command');
    }

    /**
     * @param array<string, mixed> $server
     */
    private function clientTransport(array $server, Reference $logger, ContainerBuilder $container): Definition
    {
        if ('stdio' === $server['transport']) {
            return (new Definition(StdioTransport::class))
                ->setFactory([TransportFactory::class, 'stdio'])
                ->setArguments([
                    $server['command'],
                    $server['cwd'],
                    $server['env'],
                    $server['inherit_env'],
                    $server['max_buffer_size'],
                    $logger,
                ]);
        }

        // Falls back to the SDK's PSR-18 discovery when the framework's client is unavailable.
        $httpClient = null !== $server['http_client']
            ? new Reference($server['http_client'])
            : new Reference('psr18.http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE);

        return (new Definition(HttpTransport::class))
            ->setFactory([TransportFactory::class, 'http'])
            ->setArguments([
                $server['url'],
                $server['headers'],
                $httpClient,
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                $logger,
                $server['max_sse_buffer_bytes'],
            ]);
    }

    /**
     * @param array<string, mixed> $client
     * @param array<string, mixed> $server
     */
    private function clientBuilder(string $clientName, string $serverName, array $client, array $server, Reference $logger): Definition
    {
        $definition = (new Definition(McpSdkClientBuilder::class))
            ->setFactory([McpSdkClient::class, 'builder'])
            ->addMethodCall('setClientInfo', [
                $client['client_info']['name'] ?? $clientName,
                $client['client_info']['version'],
                $client['client_info']['description'],
            ])
            ->addMethodCall('setCapabilities', [new Definition(ClientCapabilities::class, [
                $client['capabilities']['roots'],
                $client['capabilities']['roots_list_changed'],
                // Advertised only when a handler backs them, otherwise the server gets "method not found".
                null !== $client['sampling'],
                null !== $client['elicitation'],
            ])])
            ->addMethodCall('setInitTimeout', [$server['init_timeout'] ?? $client['init_timeout']])
            ->addMethodCall('setRequestTimeout', [$server['request_timeout'] ?? $client['request_timeout']])
            ->addMethodCall('setMaxRetries', [$server['max_retries'] ?? $client['max_retries']])
            ->addMethodCall('setLogger', [$logger])
            ->addTag('monolog.logger', ['channel' => 'mcp']);

        if (null !== $client['protocol_version']) {
            $definition->addMethodCall('setProtocolVersion', [
                (new Definition(ProtocolVersion::class))
                    ->setFactory([ProtocolVersion::class, 'from'])
                    ->setArguments([$client['protocol_version']]),
            ]);
        }

        if (null !== $client['sampling']) {
            $definition->addMethodCall('addRequestHandler', [
                new Definition(SamplingRequestHandler::class, [new Reference($client['sampling']), $logger]),
            ]);
        }

        if (null !== $client['elicitation']) {
            $definition->addMethodCall('addRequestHandler', [
                new Definition(ElicitationRequestHandler::class, [new Reference($client['elicitation']), $logger]),
            ]);
        }

        if ($client['forward_server_logs']) {
            $definition->addMethodCall('addNotificationHandler', [
                new Definition(LoggingNotificationHandler::class, [
                    new Definition(ServerLogForwarder::class, [$clientName, $serverName, $logger]),
                ]),
            ]);
        }

        return $definition;
    }

    /**
     * @param array<string, array<string, mixed>> $servers
     */
    private function configureServers(array $servers, ContainerBuilder $container): void
    {
        $this->assertServersAreIsolated($servers);

        $elements = [];
        foreach ($servers as $name => $server) {
            foreach (array_keys(self::ELEMENT_KINDS) as $kind) {
                // Normalized so a leading backslash in the configuration is irrelevant.
                $elements[$name][$kind] = array_values(array_map(
                    static fn (string $pattern): string => ltrim($pattern, '\\'),
                    $server[$kind],
                ));
            }

            $this->configureServer($name, $server, $container);
        }

        // The compiler passes are registered in build(), before this extension is loaded, so a
        // container parameter is the only channel to hand them the per-server element lists.
        $container->setParameter('mcp.servers.elements', $elements);
        // Overwritten by McpPass with the elements no server exposes.
        $container->setParameter('mcp.servers.unassigned', []);

        $this->registerServerLocator('mcp.server_locator.builder', array_keys($servers), 'builder', $container);
        $this->registerServerLocator('mcp.server_locator.registry', array_keys($servers), 'registry', $container);

        $this->configureRouting($servers, $container);
        $this->configureCommands($servers, $container);
        $this->configureProfiler($servers, $container);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function configureServer(string $name, array $server, ContainerBuilder $container): void
    {
        $registryId = \sprintf('mcp.server.%s.registry', $name);
        $builderId = \sprintf('mcp.server.%s.builder', $name);
        $sessionId = $this->configureSessionStore($name, $server['session'], $container);

        $container->register($registryId, Registry::class)
            ->setArguments([new Reference('event_dispatcher'), new Reference('logger')])
            ->addTag('monolog.logger', ['channel' => 'mcp']);

        $container->register($builderId, Builder::class)
            ->setFactory([Server::class, 'builder'])
            ->addMethodCall('setServerInfo', [
                $server['name'] ?? $name,
                $server['version'],
                $server['description'],
                $this->iconDefinitions($server['icons']),
                $server['website_url'],
            ])
            ->addMethodCall('setPaginationLimit', [$server['pagination_limit']])
            ->addMethodCall('setInstructions', [$server['instructions']])
            ->addMethodCall('setEventDispatcher', [new Reference('event_dispatcher')])
            ->addMethodCall('setRegistry', [new Reference($registryId)])
            ->addMethodCall('setSession', [new Reference($sessionId)])
            ->addMethodCall('addRequestHandlers', [new TaggedIteratorArgument('mcp.request_handler')])
            ->addMethodCall('addNotificationHandlers', [new TaggedIteratorArgument('mcp.notification_handler')])
            ->addMethodCall('addLoaders', [new TaggedIteratorArgument('mcp.loader')])
            ->addMethodCall('setLogger', [new Reference('logger')])
            ->addTag('mcp.server.builder', ['server' => $name])
            ->addTag('monolog.logger', ['channel' => 'mcp']);

        $container->register('mcp.server.'.$name, Server::class)
            ->setFactory([new Reference($builderId), 'build']);

        $container->registerAliasForArgument('mcp.server.'.$name, Server::class, $name.' server');

        if (!$server['transports']['http']) {
            return;
        }

        $container->register(\sprintf('mcp.server.%s.middleware_factory', $name), MiddlewareFactory::class)
            ->setArguments([$server['http']['allowed_hosts']]);

        $container->register(\sprintf('mcp.server.%s.controller', $name), McpController::class)
            ->setArguments([
                new Reference('mcp.server.'.$name),
                new Reference('mcp.psr_http_factory'),
                new Reference('mcp.http_foundation_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference(\sprintf('mcp.server.%s.middleware_factory', $name)),
                new Reference('logger'),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments')
            ->addTag('monolog.logger', ['channel' => 'mcp']);
    }

    /**
     * @param array<string, array<string, mixed>> $servers
     */
    private function configureRouting(array $servers, ContainerBuilder $container): void
    {
        $routes = [];
        foreach ($servers as $name => $server) {
            if (!$server['transports']['http']) {
                continue;
            }

            $routes[] = [
                'name' => $name,
                'path' => $this->httpPath($name, $server),
                'controller' => \sprintf('mcp.server.%s.controller::handle', $name),
            ];
        }

        $container->register('mcp.server.route_loader', RouteLoader::class)
            ->setArguments([$routes])
            ->addTag('routing.loader');
    }

    /**
     * @param array<string, array<string, mixed>> $servers
     */
    private function configureCommands(array $servers, ContainerBuilder $container): void
    {
        $container->register('mcp.server.debug_command', DebugCommand::class)
            ->setArguments([
                new Reference('mcp.server_locator.builder'),
                new Reference('mcp.server_locator.registry'),
                // Filled by McpPass, which runs after this extension; the placeholder is resolved later.
                '%mcp.servers.unassigned%',
            ])
            ->addTag('console.command');

        $stdio = array_filter($servers, static fn (array $server): bool => $server['transports']['stdio']);
        if ([] === $stdio) {
            return;
        }

        $container->register('mcp.server.command', McpCommand::class)
            ->setArguments([
                $this->registerServerLocator('mcp.server_locator.stdio', array_keys($stdio), null, $container),
                new Reference('logger'),
            ])
            ->addTag('console.command')
            ->addTag('monolog.logger', ['channel' => 'mcp']);
    }

    /**
     * @param array<string, array<string, mixed>> $servers
     */
    private function configureProfiler(array $servers, ContainerBuilder $container): void
    {
        if (!$container->getParameter('kernel.debug')) {
            return;
        }

        // The collector builds every server itself so each profiled request shows the full
        // capability set, not only the one that happened to serve an MCP request.
        $container->setDefinition('mcp.data_collector', (new Definition(DataCollector::class))
            ->setArguments([
                new Reference('mcp.server_locator.builder'),
                new Reference('mcp.server_locator.registry'),
            ])
            ->addTag('data_collector', ['id' => 'mcp']));
    }

    /**
     * @param list<string> $names
     */
    private function registerServerLocator(string $locatorId, array $names, ?string $suffix, ContainerBuilder $container): Reference
    {
        $references = [];
        foreach ($names as $name) {
            $references[$name] = new Reference(null === $suffix ? 'mcp.server.'.$name : \sprintf('mcp.server.%s.%s', $name, $suffix));
        }

        $container->register($locatorId, ServiceLocator::class)
            ->setArguments([$references])
            ->addTag('container.service_locator');

        return new Reference($locatorId);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function httpPath(string $name, array $server): string
    {
        // Always derived from the name: a count-dependent default ("/mcp" for the only server")
        // would silently move an existing endpoint the day a second server is added.
        return $server['http']['path'] ?? '/mcp/'.$name;
    }

    /**
     * Session ids are not namespaced by server and the SDK's session manager accepts any id
     * present in the store, so two servers sharing a store means a session minted on one is
     * valid on the other — across a firewall boundary that is privilege escalation.
     *
     * @param array<string, array<string, mixed>> $servers
     */
    private function assertServersAreIsolated(array $servers): void
    {
        $paths = [];
        $stores = [];

        foreach ($servers as $name => $server) {
            if ($server['transports']['http']) {
                $path = $this->httpPath($name, $server);
                if (isset($paths[$path])) {
                    throw new LogicException(\sprintf('The MCP servers "%s" and "%s" are both configured on the HTTP path "%s". Give each server its own "http.path".', $paths[$path], $name, $path));
                }
                $paths[$path] = $name;
            }

            $session = $server['session'];
            $key = match ($session['store']) {
                'file' => 'file:'.($session['directory'] ?? '%kernel.cache_dir%/mcp-sessions/'.$name),
                'cache' => 'cache:'.$session['cache_pool'].':'.($session['prefix'] ?? 'mcp-'.$name.'-'),
                'framework' => 'framework:'.($session['prefix'] ?? 'mcp-'.$name.'-'),
                default => null,
            };

            if (null === $key) {
                continue;
            }

            if (isset($stores[$key])) {
                throw new LogicException(\sprintf('The MCP servers "%s" and "%s" share the same session storage. Sessions are not namespaced by server, so a session created on one would be accepted by the other; give each server its own "session.directory" or "session.prefix".', $stores[$key], $name));
            }
            $stores[$key] = $name;
        }
    }

    /**
     * @param list<array{src: string, mime_type: string|null, sizes: list<string>}> $icons
     *
     * @return list<Definition>|null
     */
    private function iconDefinitions(array $icons): ?array
    {
        if ([] === $icons) {
            // Not an empty array: the SDK would advertise "icons": [] in the initialize result.
            return null;
        }

        return array_map(
            static fn (array $icon): Definition => new Definition(Icon::class, [$icon['src'], $icon['mime_type'], $icon['sizes']]),
            $icons,
        );
    }

    private function configureApps(ContainerBuilder $builder): void
    {
        $builder->registerAttributeForAutoconfiguration(
            AsMcpApp::class,
            static function (ChildDefinition $definition, AsMcpApp $attribute, \Reflector $reflector): void {
                $definition->addTag('mcp.app');
            }
        );

        // The Twig-backed renderer (used for template-based apps) is only available when Twig is.
        // Aliased to its class so custom #[AsMcpApp] handlers can autowire it.
        if (class_exists(Environment::class)) {
            $builder->register(McpAppRenderer::SERVICE_ID, McpAppRenderer::class)
                ->setArguments([new Reference('twig')]);
            $builder->setAlias(McpAppRenderer::class, McpAppRenderer::SERVICE_ID);
        }
    }

    /**
     * The tag records which method carries the attribute, so {@see McpPass} can reflect it at compile
     * time and register the element with the server builder (replacing file-based discovery).
     */
    private function registerMcpAttributes(ContainerBuilder $builder): void
    {
        $mcpAttributes = [
            McpTool::class => 'mcp.tool',
            McpPrompt::class => 'mcp.prompt',
            McpResource::class => 'mcp.resource',
            McpResourceTemplate::class => 'mcp.resource_template',
        ];

        foreach ($mcpAttributes as $attributeClass => $tag) {
            $builder->registerAttributeForAutoconfiguration(
                $attributeClass,
                static function (ChildDefinition $definition, object $attribute, \Reflector $reflector) use ($tag, $attributeClass): void {
                    if ($reflector instanceof \ReflectionMethod) {
                        $definition->addTag($tag, ['method' => $reflector->getName()]);

                        return;
                    }

                    if ($reflector instanceof \ReflectionClass && !$reflector->hasMethod('__invoke')) {
                        throw new LogicException(\sprintf('The class "%s" uses #[%s] as a class-level attribute but has no "__invoke()" method. Add an __invoke() method or move the attribute to a method.', $reflector->getName(), $attributeClass));
                    }

                    $definition->addTag($tag, ['method' => '__invoke']);
                }
            );
        }
    }

    /**
     * @param array{store: string, directory: string|null, cache_pool: string, prefix: string|null, ttl: int} $session
     */
    private function configureSessionStore(string $name, array $session, ContainerBuilder $container): string
    {
        $id = \sprintf('mcp.server.%s.session.store', $name);
        $prefix = $session['prefix'] ?? \sprintf('mcp-%s-', $name);

        if ('memory' === $session['store']) {
            $container->register($id, InMemorySessionStore::class)
                ->setArguments([$session['ttl']]);

            return $id;
        }

        if ('cache' === $session['store']) {
            $cachePoolId = $session['cache_pool'];

            // Create the default cache pool as a PSR-16 wrapper around cache.app if it doesn't exist
            if ('cache.mcp.sessions' === $cachePoolId && !$container->hasDefinition($cachePoolId) && !$container->hasAlias($cachePoolId)) {
                $container->register($cachePoolId, Psr16Cache::class)
                    ->setArguments([new Reference('cache.app')]);
            }

            $container->register($id, Psr16SessionStore::class)
                ->setArguments([new Reference($cachePoolId), $prefix, $session['ttl']]);

            return $id;
        }

        if ('framework' === $session['store']) {
            $container->register($id, FrameworkSessionStore::class)
                ->setArguments([new Reference('session.handler'), $prefix, $session['ttl']]);

            return $id;
        }

        $container->register($id, FileSessionStore::class)
            ->setArguments([$session['directory'] ?? '%kernel.cache_dir%/mcp-sessions/'.$name, $session['ttl']]);

        return $id;
    }
}
