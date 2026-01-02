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
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Controller\OAuthMetadataController;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\EventListener\OAuthUnauthorizedListener;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\AI\McpBundle\Security\Attribute\RequireScope;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
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
                ->addTag('data_collector');
            $builder->setDefinition('mcp.data_collector', $dataCollector);
        }

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $config['http'], $config['oauth'] ?? [], $builder);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new McpPass());
    }

    private function registerMcpAttributes(ContainerBuilder $builder): void
    {
        $builder->registerAttributeForAutoconfiguration(
            McpTool::class,
            static function (ChildDefinition $definition, McpTool $attribute, \Reflector $reflector): void {
                $name = $attribute->name ?? ($reflector instanceof \ReflectionMethod ? $reflector->getName() : null);
                $definition->addTag('mcp.tool', ['name' => $name]);
            }
        );

        $builder->registerAttributeForAutoconfiguration(
            McpPrompt::class,
            static function (ChildDefinition $definition, McpPrompt $attribute, \Reflector $reflector): void {
                $name = $attribute->name ?? ($reflector instanceof \ReflectionMethod ? $reflector->getName() : null);
                $definition->addTag('mcp.prompt', ['name' => $name]);
            }
        );

        $builder->registerAttributeForAutoconfiguration(
            McpResource::class,
            static function (ChildDefinition $definition, McpResource $attribute, \Reflector $reflector): void {
                $definition->addTag('mcp.resource', ['uri' => $attribute->uri]);
            }
        );

        $builder->registerAttributeForAutoconfiguration(
            McpResourceTemplate::class,
            static function (ChildDefinition $definition, McpResourceTemplate $attribute, \Reflector $reflector): void {
                $definition->addTag('mcp.resource_template', ['uri' => $attribute->uriTemplate]);
            }
        );

        // Register RequireScope attribute for scope checking
        $builder->registerAttributeForAutoconfiguration(
            RequireScope::class,
            static function (ChildDefinition $definition, RequireScope $attribute, \Reflector $reflector): void {
                $method = $reflector instanceof \ReflectionMethod ? $reflector->getName() : '__invoke';
                $definition->addTag('mcp.require_scope', [
                    'scopes' => $attribute->scopes,
                    'method' => $method,
                ]);
            }
        );
    }

    /**
     * @param array{stdio: bool, http: bool}                                                                                               $transports
     * @param array{path: string, session: array{store: string, directory: string, ttl: int}}                                              $httpConfig
     * @param array{enabled?: bool, authorization_servers?: list<string>, resource?: string|null, scopes_supported?: list<string>}|array{} $oauthConfig
     */
    private function configureClient(array $transports, array $httpConfig, array $oauthConfig, ContainerBuilder $container): void
    {
        if (!$transports['stdio'] && !$transports['http']) {
            return;
        }

        $oauthEnabled = $oauthConfig['enabled'] ?? false;

        // Register PSR factories
        $container->register('mcp.psr17_factory', Psr17Factory::class);

        $container->register('mcp.psr_http_factory', PsrHttpFactory::class)
            ->setArguments([
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
                new Reference('mcp.psr17_factory'),
            ]);

        $container->register('mcp.http_foundation_factory', HttpFoundationFactory::class);

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
                $oauthEnabled,
            ])
            ->addTag('routing.loader');

        $container->setParameter('mcp.http.path', $httpConfig['path']);

        if ($oauthEnabled) {
            $this->configureOAuth($oauthConfig, $httpConfig['path'], $container);
        }
    }

    /**
     * @param array{enabled?: bool, authorization_servers?: list<string>, resource?: string|null, scopes_supported?: list<string>} $oauthConfig
     */
    private function configureOAuth(array $oauthConfig, string $mcpPath, ContainerBuilder $container): void
    {
        // RFC 9728: Protected Resource Metadata controller
        $container->register('mcp.oauth.metadata_controller', OAuthMetadataController::class)
            ->setArguments([
                $oauthConfig['authorization_servers'] ?? [],
                $oauthConfig['resource'] ?? null,
                $mcpPath,
                $oauthConfig['scopes_supported'] ?? [],
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        // RFC 6750: WWW-Authenticate header on 401 responses
        $container->register('mcp.oauth.unauthorized_listener', OAuthUnauthorizedListener::class)
            ->setArguments([$mcpPath])
            ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
    }

    /**
     * @param array{store: string, directory: string, ttl: int} $sessionConfig
     */
    private function configureSessionStore(array $sessionConfig, ContainerBuilder $container): void
    {
        if ('memory' === $sessionConfig['store']) {
            $container->register('mcp.session.store', InMemorySessionStore::class)
                ->setArguments([$sessionConfig['ttl']]);
        } else {
            $container->register('mcp.session.store', FileSessionStore::class)
                ->setArguments([$sessionConfig['directory'], $sessionConfig['ttl']]);
        }
    }
}
