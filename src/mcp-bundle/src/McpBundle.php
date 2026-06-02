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
use Symfony\AI\McpBundle\OAuth\NotFoundRequestHandler;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\AI\McpBundle\Security\AccessTokenAuthenticator;
use Symfony\AI\McpBundle\Security\SecurityResourceOwnerResolver;
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
     * Wires the native OAuth 2.1 authorization server and returns the route
     * definitions to register.
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

        $privateKey = $oauth['signing_key']['private_key'] ?? null;
        if (!\is_string($privateKey) || '' === $privateKey) {
            throw new InvalidConfigurationException('The "mcp.oauth.signing_key.private_key" option is required when OAuth is enabled.');
        }

        $this->registerPsrFactories($container);

        $oauthNs = 'Mcp\\Server\\Transport\\Http\\OAuth\\';
        $middlewareNs = 'Mcp\\Server\\Transport\\Http\\Middleware\\';

        $resource = \is_string($oauth['resource'] ?? null) && '' !== $oauth['resource'] ? $oauth['resource'] : $issuer.$mcpPath;
        $scopes = $oauth['scopes'];
        $endpoints = $oauth['endpoints'];

        $psr17 = new Reference('mcp.psr17_factory');

        // Signing key + JWKS
        $container->register('mcp.oauth.signing_key', $oauthNs.'RsaSigningKey')
            ->setFactory([$oauthNs.'RsaSigningKey', 'fromFile'])
            ->setArguments([$privateKey, $oauth['signing_key']['key_id'] ?? null, $oauth['signing_key']['passphrase'] ?? null]);

        // Storage (PSR-16 over the configured cache pool)
        $container->register('mcp.oauth.cache', Psr16Cache::class)
            ->setArguments([new Reference($oauth['cache_pool'])]);
        $container->register('mcp.oauth.client_repository', $oauthNs.'CacheClientRepository')
            ->setArguments([new Reference('mcp.oauth.cache')]);
        $container->register('mcp.oauth.authorization_code_store', $oauthNs.'CacheAuthorizationCodeStore')
            ->setArguments([new Reference('mcp.oauth.cache')]);
        $container->register('mcp.oauth.refresh_token_store', $oauthNs.'CacheRefreshTokenStore')
            ->setArguments([new Reference('mcp.oauth.cache')]);

        // Token issuance + validation
        $container->register('mcp.oauth.access_token_issuer', $oauthNs.'JwtAccessTokenIssuer')
            ->setArguments([new Reference('mcp.oauth.signing_key'), $issuer]);
        $container->register('mcp.oauth.jwks_provider', $oauthNs.'StaticJwksProvider')
            ->setArguments([new Reference('mcp.oauth.signing_key')]);
        $container->register('mcp.oauth.token_validator', $oauthNs.'JwtTokenValidator')
            ->setArguments([$issuer, [$resource, $issuer], new Reference('mcp.oauth.jwks_provider')]);
        $container->setAlias($oauthNs.'AuthorizationTokenValidatorInterface', 'mcp.oauth.token_validator');

        // Engine
        $container->register('mcp.oauth.code_issuer', $oauthNs.'NativeAuthorizationCodeIssuer')
            ->setArguments([new Reference('mcp.oauth.authorization_code_store')]);
        $container->register('mcp.oauth.token_granter', $oauthNs.'NativeTokenGranter')
            ->setArguments([
                new Reference('mcp.oauth.client_repository'),
                new Reference('mcp.oauth.authorization_code_store'),
                new Reference('mcp.oauth.refresh_token_store'),
                new Reference('mcp.oauth.access_token_issuer'),
                $resource,
                $oauth['access_token_ttl'],
                $oauth['refresh_token_ttl'],
            ]);
        $container->register('mcp.oauth.client_registrar', $oauthNs.'StoredClientRegistrar')
            ->setArguments([new Reference('mcp.oauth.client_repository'), $scopes]);

        // Discovery metadata
        $container->register('mcp.oauth.authorization_server_metadata', $oauthNs.'AuthorizationServerMetadata')
            ->setArguments([$issuer, $issuer.$endpoints['authorize'], $issuer.$endpoints['token'], $issuer.'/.well-known/jwks.json', $issuer.$endpoints['register'], $scopes]);
        $container->register('mcp.oauth.protected_resource_metadata', $oauthNs.'ProtectedResourceMetadata')
            ->setArguments([[$issuer], $scopes, $resource]);

        // Resource owner + consent (overridable via service ids)
        if (\is_string($oauth['resource_owner_resolver'] ?? null)) {
            $container->setAlias('mcp.oauth.resource_owner_resolver', $oauth['resource_owner_resolver']);
        } else {
            $container->register('mcp.oauth.resource_owner_resolver', SecurityResourceOwnerResolver::class)
                ->setArguments([new Reference('security.token_storage'), $psr17, $oauth['login_path']]);
        }
        if (\is_string($oauth['consent'] ?? null)) {
            $container->setAlias('mcp.oauth.consent', $oauth['consent']);
        } else {
            $container->register('mcp.oauth.consent', $oauthNs.'AutoApproveConsent');
        }

        // Endpoint middlewares + bridge handler
        $container->register('mcp.oauth.authorize_middleware', $middlewareNs.'AuthorizationEndpointMiddleware')
            ->setArguments([
                new Reference('mcp.oauth.client_repository'),
                new Reference('mcp.oauth.code_issuer'),
                new Reference('mcp.oauth.resource_owner_resolver'),
                new Reference('mcp.oauth.consent'),
                $scopes,
                $endpoints['authorize'],
                $psr17,
                $psr17,
            ]);
        $container->register('mcp.oauth.token_middleware', $middlewareNs.'TokenEndpointMiddleware')
            ->setArguments([new Reference('mcp.oauth.token_granter'), $endpoints['token'], $psr17, $psr17]);
        $container->register('mcp.oauth.not_found_handler', NotFoundRequestHandler::class)
            ->setArguments([$psr17]);

        // Controller
        $container->register('mcp.oauth.controller', OAuthController::class)
            ->setArguments([
                new Reference('mcp.oauth.authorize_middleware'),
                new Reference('mcp.oauth.token_middleware'),
                new Reference('mcp.oauth.not_found_handler'),
                new Reference('mcp.psr_http_factory'),
                new Reference('mcp.http_foundation_factory'),
                new Reference('mcp.oauth.client_registrar'),
                new Reference('mcp.oauth.authorization_server_metadata'),
                new Reference('mcp.oauth.protected_resource_metadata'),
                new Reference('mcp.oauth.signing_key'),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        // Firewall authenticator (referenced by class id in security.yaml)
        $container->register(AccessTokenAuthenticator::class, AccessTokenAuthenticator::class)
            ->setArguments([new Reference('mcp.oauth.token_validator'), $mcpPath]);

        $routes = [
            ['name' => 'mcp_oauth_authorize', 'path' => $endpoints['authorize'], 'controller' => 'mcp.oauth.controller::authorize', 'methods' => ['GET', 'POST']],
            ['name' => 'mcp_oauth_token', 'path' => $endpoints['token'], 'controller' => 'mcp.oauth.controller::token', 'methods' => ['POST']],
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
