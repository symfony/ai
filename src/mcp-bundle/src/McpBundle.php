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
use Mcp\Server\Transport\Http\Middleware\AuthorizationMiddleware;
use Mcp\Server\Transport\Http\Middleware\ClientRegistrationMiddleware;
use Mcp\Server\Transport\Http\Middleware\OAuthProxyMiddleware;
use Mcp\Server\Transport\Http\Middleware\OAuthRequestMetaMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtectedResourceMetadataMiddleware;
use Mcp\Server\Transport\Http\OAuth\AuthorizationTokenValidatorInterface;
use Mcp\Server\Transport\Http\OAuth\ClientRegistrarInterface;
use Mcp\Server\Transport\Http\OAuth\JwksProvider;
use Mcp\Server\Transport\Http\OAuth\JwtTokenValidator;
use Mcp\Server\Transport\Http\OAuth\OidcDiscovery;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;
use Psr\Http\Server\MiddlewareInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\Handler\FilteredListToolsHandler;
use Symfony\AI\McpBundle\Middleware\SymfonySecurityMiddleware;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\AI\McpBundle\Security\IsGrantedChecker;
use Symfony\AI\McpBundle\Security\SecurityReferenceHandler;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class McpBundle extends AbstractBundle
{
    private const DEFAULT_OAUTH_ROUTES = [
        '/.well-known/oauth-protected-resource',
        '/.well-known/oauth-authorization-server',
        '/authorize',
        '/token',
        '/register',
    ];

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

        $this->registerParameters($config, $builder);
        $this->registerAutoconfiguration($builder);
        $this->configureReferenceHandler($config, $builder);
        $this->configureDebug($builder);

        if (isset($config['client_transports'])) {
            $httpConfig = $config['http'];

            $this->configureTransports($config['client_transports'], $httpConfig, $builder);
            $this->configureSecurity($builder);

            if ($httpConfig['oauth']['enabled']) {
                $this->configureOAuth($httpConfig['oauth'], $httpConfig['path'], $builder);
            }
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new McpPass());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerParameters(array $config, ContainerBuilder $builder): void
    {
        $builder->setParameter('mcp.app', $config['app']);
        $builder->setParameter('mcp.version', $config['version']);
        $builder->setParameter('mcp.description', $config['description']);
        $builder->setParameter('mcp.website_url', $config['website_url']);
        $builder->setParameter('mcp.icons', $config['icons']);
        $builder->setParameter('mcp.pagination_limit', $config['pagination_limit']);
        $builder->setParameter('mcp.instructions', $config['instructions']);
        $builder->setParameter('mcp.discovery.scan_dirs', $config['discovery']['scan_dirs']);
        $builder->setParameter('mcp.discovery.exclude_dirs', $config['discovery']['exclude_dirs']);

        $oauthEnabled = $config['http']['oauth']['enabled'] ?? false;
        $routes = $config['http']['routes'];
        if ([] === $routes && $oauthEnabled) {
            $routes = self::DEFAULT_OAUTH_ROUTES;
        }
        $builder->setParameter('mcp.http.routes', $routes);
    }

    private function registerAutoconfiguration(ContainerBuilder $builder): void
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

        $builder->registerForAutoconfiguration(LoaderInterface::class)
            ->addTag('mcp.loader');

        $builder->registerForAutoconfiguration(RequestHandlerInterface::class)
            ->addTag('mcp.request_handler');

        $builder->registerForAutoconfiguration(NotificationHandlerInterface::class)
            ->addTag('mcp.notification_handler');

        $builder->registerForAutoconfiguration(MiddlewareInterface::class)
            ->addTag('mcp.middleware');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureReferenceHandler(array $config, ContainerBuilder $builder): void
    {
        $referenceHandler = $config['reference_handler'];
        if (null === $referenceHandler && ($config['http']['oauth']['enabled'] ?? false)) {
            $referenceHandler = 'mcp.security_reference_handler';
        }
        if (null !== $referenceHandler) {
            $builder->getDefinition('mcp.server.builder')
                ->addMethodCall('setReferenceHandler', [new Reference($referenceHandler)]);
        }
    }

    private function configureDebug(ContainerBuilder $builder): void
    {
        if (!$builder->getParameter('kernel.debug')) {
            return;
        }

        $traceableRegistry = (new Definition('mcp.traceable_registry'))
            ->setClass(TraceableRegistry::class)
            ->setArguments([new Reference('.inner')])
            ->setDecoratedService('mcp.registry')
            ->addTag('kernel.reset', ['method' => 'reset']);
        $builder->setDefinition('mcp.traceable_registry', $traceableRegistry);

        $dataCollector = (new Definition(DataCollector::class))
            ->setArguments([new Reference('mcp.traceable_registry')])
            ->addTag('data_collector', ['id' => 'mcp']);
        $builder->setDefinition('mcp.data_collector', $dataCollector);
    }

    /**
     * @param array<string, bool>  $transports
     * @param array<string, mixed> $httpConfig
     */
    private function configureTransports(array $transports, array $httpConfig, ContainerBuilder $container): void
    {
        if (!$transports['stdio'] && !$transports['http']) {
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
                    new TaggedIteratorArgument('mcp.middleware'),
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
                '%mcp.http.routes%',
            ])
            ->addTag('routing.loader');
    }

    private function configureSecurity(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('security.authorization_checker') && !$container->hasAlias('security.authorization_checker')) {
            return;
        }

        $container->register('mcp.is_granted_checker', IsGrantedChecker::class)
            ->setArguments([new Reference('security.authorization_checker')]);

        $container->register(FilteredListToolsHandler::class)
            ->setArguments([
                new Reference('mcp.registry'),
                new Reference('mcp.is_granted_checker'),
                new Reference('security.token_storage'),
            ])
            ->setAutoconfigured(true);
    }

    /**
     * @param array<string, mixed> $oauthConfig
     */
    private function configureOAuth(array $oauthConfig, string $path, ContainerBuilder $container): void
    {
        foreach (['issuer', 'base_url'] as $required) {
            if (null === ($oauthConfig[$required] ?? null) || '' === $oauthConfig[$required]) {
                throw new LogicException(\sprintf('The "mcp.http.oauth.%s" option is required when OAuth is enabled.', $required));
            }
        }

        $audience = rtrim($oauthConfig['base_url'], '/').$path;

        $container->register('mcp.oauth.discovery', OidcDiscovery::class)
            ->setArguments([
                null,
                new Reference('mcp.psr17_factory'),
                new Reference(CacheInterface::class),
            ]);

        $container->register('mcp.oauth.jwks_provider', JwksProvider::class)
            ->setArguments([
                new Reference('mcp.oauth.discovery'),
                null,
                new Reference('mcp.psr17_factory'),
                new Reference(CacheInterface::class),
            ]);

        $container->register('mcp.oauth.token_validator', JwtTokenValidator::class)
            ->setArguments([
                $oauthConfig['issuer'],
                $audience,
                new Reference('mcp.oauth.jwks_provider'),
            ]);
        $container->setAlias(AuthorizationTokenValidatorInterface::class, 'mcp.oauth.token_validator');

        $container->register('mcp.oauth.resource_metadata', ProtectedResourceMetadata::class)
            ->setArguments([
                [$oauthConfig['base_url']],
                $oauthConfig['scopes'],
            ]);

        $this->registerOAuthMiddleware($oauthConfig, $container);

        $container->register('mcp.security_reference_handler', SecurityReferenceHandler::class)
            ->setArguments([
                new Reference('mcp.reference_handler'),
                new Reference('mcp.is_granted_checker'),
            ]);
    }

    /**
     * @param array<string, mixed> $oauthConfig
     */
    private function registerOAuthMiddleware(array $oauthConfig, ContainerBuilder $container): void
    {
        $container->register(ProtectedResourceMetadataMiddleware::class)
            ->setArguments([new Reference('mcp.oauth.resource_metadata')])
            ->addTag('mcp.middleware', ['priority' => 60]);

        $container->register(ClientRegistrationMiddleware::class)
            ->setArguments([
                new Reference(ClientRegistrarInterface::class),
                $oauthConfig['base_url'],
            ])
            ->addTag('mcp.middleware', ['priority' => 50]);

        $container->register(OAuthProxyMiddleware::class)
            ->setArguments([
                $oauthConfig['issuer'],
                $oauthConfig['base_url'],
                new Reference('mcp.oauth.discovery'),
            ])
            ->addTag('mcp.middleware', ['priority' => 40]);

        $container->register(AuthorizationMiddleware::class)
            ->setArguments([
                new Reference('mcp.oauth.token_validator'),
                new Reference('mcp.oauth.resource_metadata'),
            ])
            ->addTag('mcp.middleware', ['priority' => 30]);

        $container->register(SymfonySecurityMiddleware::class)
            ->setArguments([
                new Reference('security.token_storage'),
                $oauthConfig['roles_claim'],
                'mcp',
                new Reference('mcp.psr17_factory'),
            ])
            ->addTag('mcp.middleware', ['priority' => 20]);

        $container->register(OAuthRequestMetaMiddleware::class)
            ->addTag('mcp.middleware', ['priority' => 10]);
    }

    /**
     * @param array<string, mixed> $sessionConfig
     */
    private function configureSessionStore(array $sessionConfig, ContainerBuilder $container): void
    {
        if ('memory' === $sessionConfig['store']) {
            $container->register('mcp.session.store', InMemorySessionStore::class)
                ->setArguments([$sessionConfig['ttl']]);
        } elseif ('cache' === $sessionConfig['store']) {
            $cachePoolId = $sessionConfig['cache_pool'];

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
