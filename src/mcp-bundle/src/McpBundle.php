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

use Http\Discovery\Psr17Factory;
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
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Controller\OAuthController;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\AI\McpBundle\Security\AuthorizationRequestResolveListener;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class McpBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/options.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('mcp.app', $config['app']);
        $builder->setParameter('mcp.version', $config['version']);
        $builder->setParameter('mcp.description', $config['description']);
        $builder->setParameter('mcp.website_url', $config['website_url']);
        $builder->setParameter('mcp.icons', $config['icons']);
        $builder->setParameter('mcp.pagination_limit', $config['pagination_limit']);
        $builder->setParameter('mcp.instructions', $config['instructions']);
        $builder->setParameter('mcp.discovery.scan_dirs', $config['discovery']['scan_dirs']);
        $builder->setParameter('mcp.discovery.exclude_dirs', $config['discovery']['exclude_dirs']);

        $this->registerMcpAttributes($builder);

        $builder->registerForAutoconfiguration(LoaderInterface::class)
            ->addTag('mcp.loader');

        $builder->registerForAutoconfiguration(RequestHandlerInterface::class)
            ->addTag('mcp.request_handler');

        $builder->registerForAutoconfiguration(NotificationHandlerInterface::class)
            ->addTag('mcp.notification_handler');

        if ($builder->getParameter('kernel.debug')) {
            $traceableRegistry = (new Definition('mcp.traceable_registry'))
                ->setClass(TraceableRegistry::class)
                ->setArguments([new Reference('.inner')])
                ->setDecoratedService('mcp.registry');
            $builder->setDefinition('mcp.traceable_registry', $traceableRegistry);

            $dataCollector = (new Definition(DataCollector::class))
                ->setArguments([new Reference('mcp.traceable_registry')])
                ->addTag('data_collector', ['id' => 'mcp']);
            $builder->setDefinition('mcp.data_collector', $dataCollector);
        }

        $oauthRoutes = [];
        if ($config['oauth']['enabled'] ?? false) {
            $oauthRoutes = $this->configureOAuth($config['oauth'], $config['http']['path'], $builder);
        }

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $config['http'], $oauthRoutes, $builder);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new McpPass());
    }

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
                static function (ChildDefinition $definition, object $attribute, \Reflector $reflector) use ($tag): void {
                    $definition->addTag($tag);
                }
            );
        }
    }

    /**
     * @param array{stdio: bool, http: bool}                                                                                      $transports
     * @param array{path: string, session: array{store: string, directory: string, cache_pool: string, prefix: string, ttl: int}} $httpConfig
     * @param list<array{name: string, path: string, controller: string, methods: list<string>}>                                  $oauthRoutes
     */
    private function configureClient(array $transports, array $httpConfig, array $oauthRoutes, ContainerBuilder $container): void
    {
        if (!$transports['stdio'] && !$transports['http']) {
            return;
        }

        $this->registerPsrFactories($container);

        // Configure session store based on HTTP config
        $this->configureSessionStore($httpConfig['session'], $container);

        if ($transports['stdio']) {
            $container->register('mcp.server.command', McpCommand::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('logger'),
                ])
                ->addTag('console.command')
                ->addTag('monolog.logger', ['channel' => 'mcp']);
        }

        if ($transports['http']) {
            $container->register('mcp.server.controller', McpController::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('mcp.psr_http_factory'),
                    new Reference('mcp.http_foundation_factory'),
                    new Reference('mcp.psr17_factory'),
                    new Reference('mcp.psr17_factory'),
                    new Reference('logger'),
                ])
                ->setPublic(true)
                ->addTag('controller.service_arguments')
                ->addTag('monolog.logger', ['channel' => 'mcp']);
        }

        $container->register('mcp.server.route_loader', RouteLoader::class)
            ->setArguments([
                $transports['http'],
                $httpConfig['path'],
                $oauthRoutes,
            ])
            ->addTag('routing.loader');
    }

    private function registerPsrFactories(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('mcp.psr17_factory')) {
            return;
        }

        $container->register('mcp.psr17_factory', Psr17Factory::class);

        $container->register('mcp.psr_http_factory', PsrHttpFactory::class)
            ->setArguments([
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
            ]);

        $container->register('mcp.http_foundation_factory', HttpFoundationFactory::class);
    }

    /**
     * Prepends the league/oauth2-server-bundle configuration derived from the
     * "mcp.oauth" options, so the heavy authorization-server engine (token
     * issuance, PKCE, persistence, the /authorize + /token controllers and the
     * firewall authenticator) is owned by league. The bundle only adds the MCP
     * specific gaps on top (DCR, well-known metadata, JWKS).
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('league_oauth2_server')) {
            return;
        }

        $oauth = $this->collectOAuthConfig($builder);
        if (true !== ($oauth['enabled'] ?? false)) {
            return;
        }

        $privateKey = $oauth['signing_key']['private_key'] ?? null;
        $publicKey = $oauth['signing_key']['public_key'] ?? null;
        $encryptionKey = $oauth['encryption_key'] ?? null;
        if (!\is_string($privateKey) || !\is_string($publicKey) || !\is_string($encryptionKey)) {
            // loadExtension() raises a precise InvalidConfigurationException.
            return;
        }

        $scopes = $oauth['scopes'] ?? ['mcp:tools', 'mcp:resources'];

        $authorizationServer = [
            'private_key' => $privateKey,
            'encryption_key' => $encryptionKey,
            'access_token_ttl' => $this->toDateInterval($oauth['access_token_ttl'] ?? 3600),
            'refresh_token_ttl' => $this->toDateInterval($oauth['refresh_token_ttl'] ?? 1209600),
            'auth_code_ttl' => $this->toDateInterval($oauth['authorization_code_ttl'] ?? 600),
            'enable_client_credentials_grant' => false,
            'enable_password_grant' => false,
            'enable_implicit_grant' => false,
            'enable_auth_code_grant' => true,
            'enable_refresh_token_grant' => true,
            'require_code_challenge_for_public_clients' => true,
        ];
        if (\is_string($oauth['signing_key']['passphrase'] ?? null) && '' !== $oauth['signing_key']['passphrase']) {
            $authorizationServer['private_key_passphrase'] = $oauth['signing_key']['passphrase'];
        }

        $persistence = $oauth['persistence'] ?? [];
        $persistenceConfig = 'doctrine' === ($persistence['type'] ?? 'in_memory')
            ? ['doctrine' => [
                'entity_manager' => $persistence['entity_manager'] ?? 'default',
                'table_prefix' => $persistence['table_prefix'] ?? 'oauth2_',
            ]]
            : ['in_memory' => null];

        $container->extension('league_oauth2_server', [
            'authorization_server' => $authorizationServer,
            'resource_server' => ['public_key' => $publicKey],
            'scopes' => ['available' => $scopes, 'default' => $scopes],
            'persistence' => $persistenceConfig,
        ], prepend: true);
    }

    /**
     * Registers the MCP-specific OAuth endpoints (DCR, well-known metadata,
     * JWKS) and the authorization-request resolve listener, then returns the
     * route definitions. The /authorize and /token routes point at league's
     * own controllers; bearer protection is league's "oauth2" authenticator.
     *
     * @param array<string, mixed> $oauth
     *
     * @return list<array{name: string, path: string, controller: string, methods: list<string>}>
     */
    private function configureOAuth(array $oauth, string $mcpPath, ContainerBuilder $container): array
    {
        $issuer = rtrim((string) ($oauth['issuer'] ?? ''), '/');
        if ('' === $issuer) {
            throw new InvalidConfigurationException('The "mcp.oauth.issuer" option is required when OAuth is enabled.');
        }

        foreach ([
            'signing_key.private_key' => $oauth['signing_key']['private_key'] ?? null,
            'signing_key.public_key' => $oauth['signing_key']['public_key'] ?? null,
            'encryption_key' => $oauth['encryption_key'] ?? null,
        ] as $option => $value) {
            if (!\is_string($value) || '' === $value) {
                throw new InvalidConfigurationException(\sprintf('The "mcp.oauth.%s" option is required when OAuth is enabled.', $option));
            }
        }

        $resource = \is_string($oauth['resource'] ?? null) && '' !== $oauth['resource'] ? $oauth['resource'] : $issuer.$mcpPath;
        $scopes = $oauth['scopes'];
        $endpoints = $oauth['endpoints'];

        // Resolve the firewall user into the authorization request and approve it.
        $consent = \is_string($oauth['consent'] ?? null) ? new Reference($oauth['consent']) : null;
        $container->register('mcp.oauth.authorization_request_listener', AuthorizationRequestResolveListener::class)
            ->setArguments([new Reference('security.token_storage'), $oauth['login_path'], $consent])
            ->addTag('kernel.event_listener', [
                'event' => 'league.oauth2_server.event.authorization_request_resolve',
                'method' => 'onResolve',
            ]);

        // MCP gaps league does not provide: DCR + well-known metadata + JWKS.
        $container->register('mcp.oauth.controller', OAuthController::class)
            ->setArguments([
                new Reference('League\\Bundle\\OAuth2ServerBundle\\Manager\\ClientManagerInterface'),
                $issuer,
                $resource,
                $scopes,
                $oauth['signing_key']['public_key'],
                $oauth['signing_key']['key_id'] ?? null,
                [
                    'authorize' => $issuer.$endpoints['authorize'],
                    'token' => $issuer.$endpoints['token'],
                    'register' => $issuer.$endpoints['register'],
                ],
                $oauth['client_registration'],
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        $routes = [
            ['name' => 'mcp_oauth_authorize', 'path' => $endpoints['authorize'], 'controller' => 'League\\Bundle\\OAuth2ServerBundle\\Controller\\AuthorizationController::indexAction', 'methods' => ['GET', 'POST']],
            ['name' => 'mcp_oauth_token', 'path' => $endpoints['token'], 'controller' => 'League\\Bundle\\OAuth2ServerBundle\\Controller\\TokenController::indexAction', 'methods' => ['POST']],
            ['name' => 'mcp_oauth_as_metadata', 'path' => '/.well-known/oauth-authorization-server', 'controller' => 'mcp.oauth.controller::authorizationServerMetadata', 'methods' => ['GET']],
            ['name' => 'mcp_oauth_prm', 'path' => '/.well-known/oauth-protected-resource', 'controller' => 'mcp.oauth.controller::protectedResourceMetadata', 'methods' => ['GET']],
            ['name' => 'mcp_oauth_jwks', 'path' => '/.well-known/jwks.json', 'controller' => 'mcp.oauth.controller::jwks', 'methods' => ['GET']],
        ];

        if ($oauth['client_registration']) {
            $routes[] = ['name' => 'mcp_oauth_register', 'path' => $endpoints['register'], 'controller' => 'mcp.oauth.controller::register', 'methods' => ['POST']];
        }

        return $routes;
    }

    /**
     * Merges every "mcp" extension config fragment so the raw "oauth" subtree
     * (with unresolved env placeholders intact) can be read during prepend.
     *
     * @return array<string, mixed>
     */
    private function collectOAuthConfig(ContainerBuilder $builder): array
    {
        $oauth = [];
        foreach ($builder->getExtensionConfig('mcp') as $config) {
            if (!\array_key_exists('oauth', $config)) {
                continue;
            }

            $raw = $config['oauth'];
            if (\is_bool($raw)) {
                $oauth['enabled'] = $raw;
                continue;
            }

            if (\is_array($raw)) {
                $oauth = array_replace_recursive($oauth, $raw);
            }
        }

        return $oauth;
    }

    private function toDateInterval(int $seconds): string
    {
        return \sprintf('PT%dS', max(1, $seconds));
    }

    /**
     * @param array{store: string, directory: string, cache_pool: string, prefix: string, ttl: int} $sessionConfig
     */
    private function configureSessionStore(array $sessionConfig, ContainerBuilder $container): void
    {
        if ('memory' === $sessionConfig['store']) {
            $container->register('mcp.session.store', InMemorySessionStore::class)
                ->setArguments([$sessionConfig['ttl']]);
        } elseif ('cache' === $sessionConfig['store']) {
            $cachePoolId = $sessionConfig['cache_pool'];

            // Create the default cache pool as a PSR-16 wrapper around cache.app if it doesn't exist
            if ('cache.mcp.sessions' === $cachePoolId && !$container->hasDefinition($cachePoolId) && !$container->hasAlias($cachePoolId)) {
                $container->register($cachePoolId, Psr16Cache::class)
                    ->setArguments([new Reference('cache.app')]);
            }

            $container->register('mcp.session.store', Psr16SessionStore::class)
                ->setArguments([
                    new Reference($sessionConfig['cache_pool']),
                    $sessionConfig['prefix'],
                    $sessionConfig['ttl'],
                ]);
        } elseif ('framework' === $sessionConfig['store']) {
            $container->register('mcp.session.store', FrameworkSessionStore::class)
                ->setArguments([
                    new Reference('session.handler'),
                    $sessionConfig['prefix'],
                    $sessionConfig['ttl'],
                ]);
        } else {
            $container->register('mcp.session.store', FileSessionStore::class)
                ->setArguments([$sessionConfig['directory'], $sessionConfig['ttl']]);
        }
    }
}
