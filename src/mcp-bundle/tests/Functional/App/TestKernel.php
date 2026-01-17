<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Functional\App;

use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new McpBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/mcp_bundle_test/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/mcp_bundle_test/logs';
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);  // Points to mcp-bundle root
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->loadFromExtension('security', [
            'providers' => [
                'test_users' => [
                    'memory' => [
                        'users' => [],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'stateless' => true,
                    'provider' => 'test_users',
                    'access_token' => [
                        'token_handler' => TestTokenHandler::class,
                    ],
                ],
            ],
            'access_control' => [
                ['path' => '^/_mcp', 'roles' => 'ROLE_USER'],
            ],
        ]);

        $container->loadFromExtension('mcp', [
            'app' => 'test-app',
            'version' => '1.0.0',
            'client_transports' => [
                'http' => true,
            ],
            'discovery' => [
                'scan_dirs' => [
                    'tests/Functional/App/Tool',
                    'tests/Functional/App/Prompt',
                    'tests/Functional/App/Resource',
                ],
            ],
        ]);

        // Register test tools
        $container->register(Tool\PublicTool::class)
            ->setAutoconfigured(true)
            ->addTag('mcp.tool', ['name' => 'public-tool'])
            ->setPublic(true);

        $container->register(Tool\AdminTool::class)
            ->setAutoconfigured(true)
            ->addTag('mcp.tool', ['name' => 'admin-tool'])
            ->addTag('mcp.require_scope', ['scopes' => ['admin'], 'method' => 'execute'])
            ->setPublic(true);

        // Register test prompts
        $container->register(Prompt\AdminPrompt::class)
            ->setAutoconfigured(true)
            ->addTag('mcp.prompt', ['name' => 'admin-prompt'])
            ->addTag('mcp.require_scope', ['scopes' => ['admin'], 'method' => 'execute'])
            ->setPublic(true);

        // Register test resources
        $container->register(Resource\AdminResource::class)
            ->setAutoconfigured(true)
            ->addTag('mcp.resource', ['uri' => 'admin://secret'])
            ->addTag('mcp.require_scope', ['scopes' => ['admin'], 'method' => 'execute'])
            ->setPublic(true);

        $container->register(TestTokenHandler::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'mcp');
    }
}
