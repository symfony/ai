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

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Controller\OAuthController;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\AI\McpBundle\Security\AuthorizationRequestResolveListener;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

class McpBundleOAuthTest extends TestCase
{
    public function testOAuthDisabledByDefault(): void
    {
        $container = $this->buildContainer([
            'mcp' => ['client_transports' => ['http' => true]],
        ]);

        $this->assertFalse($container->hasDefinition('mcp.oauth.controller'));
        $this->assertFalse($container->hasDefinition('mcp.oauth.authorization_request_listener'));

        // RouteLoader receives an empty oauth-routes list.
        $this->assertSame([], $container->getDefinition('mcp.server.route_loader')->getArgument(2));
    }

    public function testOAuthEnabledRegistersControllerAndListener(): void
    {
        $container = $this->buildContainer($this->enabledConfig());

        $this->assertTrue($container->hasDefinition('mcp.oauth.controller'));
        $this->assertSame(OAuthController::class, $container->getDefinition('mcp.oauth.controller')->getClass());
        $this->assertTrue($container->getDefinition('mcp.oauth.controller')->isPublic());

        $controllerArgs = $container->getDefinition('mcp.oauth.controller')->getArguments();
        $this->assertSame('https://mcp.example.com', $controllerArgs[1]);
        $this->assertSame('https://mcp.example.com/_mcp', $controllerArgs[2]);

        $listener = $container->getDefinition('mcp.oauth.authorization_request_listener');
        $this->assertSame(AuthorizationRequestResolveListener::class, $listener->getClass());
        $this->assertNull($listener->getArgument(2), 'No consent service is wired by default.');
        $this->assertArrayHasKey('kernel.event_listener', $listener->getTags());
        $this->assertSame(
            'league.oauth2_server.event.authorization_request_resolve',
            $listener->getTag('kernel.event_listener')[0]['event'],
        );
    }

    public function testRoutesPointAuthorizeAndTokenToLeagueControllers(): void
    {
        $container = $this->buildContainer($this->enabledConfig());

        $routes = $container->getDefinition('mcp.server.route_loader')->getArgument(2);
        $byName = array_column($routes, 'controller', 'name');

        $this->assertSame('League\\Bundle\\OAuth2ServerBundle\\Controller\\AuthorizationController::indexAction', $byName['mcp_oauth_authorize']);
        $this->assertSame('League\\Bundle\\OAuth2ServerBundle\\Controller\\TokenController::indexAction', $byName['mcp_oauth_token']);
        $this->assertSame('mcp.oauth.controller::register', $byName['mcp_oauth_register']);
        $this->assertSame('mcp.oauth.controller::authorizationServerMetadata', $byName['mcp_oauth_as_metadata']);
        $this->assertSame('mcp.oauth.controller::protectedResourceMetadata', $byName['mcp_oauth_prm']);
        $this->assertSame('mcp.oauth.controller::jwks', $byName['mcp_oauth_jwks']);
    }

    public function testClientRegistrationCanBeDisabled(): void
    {
        $config = $this->enabledConfig();
        $config['mcp']['oauth']['client_registration'] = false;

        $container = $this->buildContainer($config);
        $names = array_column($container->getDefinition('mcp.server.route_loader')->getArgument(2), 'name');

        $this->assertNotContains('mcp_oauth_register', $names);
    }

    public function testCustomConsentServiceIsWiredIntoListener(): void
    {
        $config = $this->enabledConfig();
        $config['mcp']['oauth']['consent'] = 'app.my_consent';

        $container = $this->buildContainer($config);

        $consent = $container->getDefinition('mcp.oauth.authorization_request_listener')->getArgument(2);
        $this->assertInstanceOf(Reference::class, $consent);
        $this->assertSame('app.my_consent', (string) $consent);
    }

    public function testEnablingWithoutIssuerThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $config = $this->enabledConfig();
        unset($config['mcp']['oauth']['issuer']);

        $this->buildContainer($config);
    }

    public function testEnablingWithoutPrivateKeyThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $config = $this->enabledConfig();
        unset($config['mcp']['oauth']['signing_key']['private_key']);

        $this->buildContainer($config);
    }

    public function testEnablingWithoutPublicKeyThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $config = $this->enabledConfig();
        unset($config['mcp']['oauth']['signing_key']['public_key']);

        $this->buildContainer($config);
    }

    public function testEnablingWithoutEncryptionKeyThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $config = $this->enabledConfig();
        unset($config['mcp']['oauth']['encryption_key']);

        $this->buildContainer($config);
    }

    public function testPrependMapsLeagueConfiguration(): void
    {
        $league = $this->prependLeagueConfig($this->enabledConfig()['mcp']['oauth']);

        $this->assertSame('/tmp/private.pem', $league['authorization_server']['private_key']);
        $this->assertSame('s3cr3t-encryption-key', $league['authorization_server']['encryption_key']);
        $this->assertSame('PT3600S', $league['authorization_server']['access_token_ttl']);
        $this->assertSame('PT1209600S', $league['authorization_server']['refresh_token_ttl']);
        $this->assertSame('PT600S', $league['authorization_server']['auth_code_ttl']);
        $this->assertTrue($league['authorization_server']['enable_auth_code_grant']);
        $this->assertTrue($league['authorization_server']['enable_refresh_token_grant']);
        $this->assertTrue($league['authorization_server']['require_code_challenge_for_public_clients']);
        $this->assertFalse($league['authorization_server']['enable_password_grant']);
        $this->assertFalse($league['authorization_server']['enable_client_credentials_grant']);
        $this->assertFalse($league['authorization_server']['enable_implicit_grant']);

        $this->assertSame('/tmp/public.pem', $league['resource_server']['public_key']);
        $this->assertSame(['mcp:tools', 'mcp:resources'], $league['scopes']['available']);
        $this->assertSame(['mcp:tools', 'mcp:resources'], $league['scopes']['default']);
        $this->assertArrayHasKey('in_memory', $league['persistence']);
    }

    public function testPrependUsesDoctrinePersistence(): void
    {
        $oauth = $this->enabledConfig()['mcp']['oauth'];
        $oauth['persistence'] = ['type' => 'doctrine', 'entity_manager' => 'oauth', 'table_prefix' => 'mcp_oauth_'];

        $league = $this->prependLeagueConfig($oauth);

        $this->assertSame('oauth', $league['persistence']['doctrine']['entity_manager']);
        $this->assertSame('mcp_oauth_', $league['persistence']['doctrine']['table_prefix']);
        $this->assertArrayNotHasKey('in_memory', $league['persistence']);
    }

    public function testPrependSkippedWhenDisabled(): void
    {
        $container = $this->newContainer();
        $container->registerExtension($this->leagueExtensionStub());
        $container->prependExtensionConfig('mcp', ['oauth' => ['enabled' => false]]);

        $this->runPrepend($container);

        $this->assertSame([], $container->getExtensionConfig('league_oauth2_server'));
    }

    /**
     * @return array<string, mixed>
     */
    private function enabledConfig(): array
    {
        return [
            'mcp' => [
                'client_transports' => ['http' => true],
                'oauth' => [
                    'enabled' => true,
                    'issuer' => 'https://mcp.example.com',
                    'encryption_key' => 's3cr3t-encryption-key',
                    'signing_key' => [
                        'private_key' => '/tmp/private.pem',
                        'public_key' => '/tmp/public.pem',
                    ],
                ],
            ],
        ];
    }

    /**
     * Runs the bundle prepend pass and returns the resulting league config.
     *
     * @param array<string, mixed> $oauth
     *
     * @return array<string, mixed>
     */
    private function prependLeagueConfig(array $oauth): array
    {
        $container = $this->newContainer();
        $container->registerExtension($this->leagueExtensionStub());
        $container->prependExtensionConfig('mcp', ['oauth' => $oauth]);

        $this->runPrepend($container);

        $configs = $container->getExtensionConfig('league_oauth2_server');
        $this->assertNotSame([], $configs, 'The league configuration should be prepended.');

        return $configs[0];
    }

    private function runPrepend(ContainerBuilder $container): void
    {
        $extension = (new McpBundle())->getContainerExtension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
    }

    private function leagueExtensionStub(): Extension
    {
        return new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'league_oauth2_server';
            }
        };
    }

    private function newContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setParameter('kernel.project_dir', '/path/to/project');

        return $container;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function buildContainer(array $configuration): ContainerBuilder
    {
        $container = $this->newContainer();

        $extension = (new McpBundle())->getContainerExtension();
        $extension->load($configuration, $container);

        return $container;
    }
}
