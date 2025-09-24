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
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\InMemorySessionStore;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\DependencyInjection\McpPass;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
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

        $this->registerMcpAttributes($builder);

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $config['http'], $builder);
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
                static function (ChildDefinition $definition) use ($tag): void {
                    $definition->addTag($tag);
                }
            );
        }
    }

    /**
     * @param array{stdio: bool, http: bool}                                                  $transports
     * @param array{path: string, session: array{store: string, directory: string, ttl: int}} $httpConfig
     */
    private function configureClient(array $transports, array $httpConfig, ContainerBuilder $container): void
    {
        if (!$transports['stdio'] && !$transports['http']) {
            return;
        }

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
                ->addTag('console.command');
        }

        if ($transports['http']) {
            $container->register('mcp.server.controller', McpController::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('mcp.psr_http_factory'),
                    new Reference('mcp.http_foundation_factory'),
                    new Reference('mcp.psr17_factory'),
                    new Reference('mcp.psr17_factory'),
                    new Reference('monolog.logger.mcp', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->setPublic(true)
                ->addTag('controller.service_arguments');
        }

        $container->register('mcp.server.route_loader', RouteLoader::class)
            ->setArguments([
                $transports['http'],
                $httpConfig['path'],
            ])
            ->addTag('routing.loader');
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
