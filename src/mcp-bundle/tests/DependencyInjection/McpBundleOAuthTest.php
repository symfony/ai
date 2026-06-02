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
use Symfony\AI\McpBundle\Security\AccessTokenAuthenticator;
use Symfony\AI\McpBundle\Security\SecurityResourceOwnerResolver;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class McpBundleOAuthTest extends TestCase
{
    public function testOAuthDisabledByDefault(): void
    {
        $container = $this->buildContainer([
            'mcp' => ['client_transports' => ['http' => true]],
        ]);

        $this->assertFalse($container->hasDefinition('mcp.oauth.controller'));
        $this->assertFalse($container->hasDefinition(AccessTokenAuthenticator::class));

        // RouteLoader receives an empty oauth-routes list.
        $this->assertSame([], $container->getDefinition('mcp.server.route_loader')->getArgument(2));
    }

    public function testOAuthEnabledRegistersEngineAndEndpoints(): void
    {
        $container = $this->buildContainer($this->enabledConfig());

        foreach ([
            'mcp.oauth.signing_key',
            'mcp.oauth.client_repository',
            'mcp.oauth.authorization_code_store',
            'mcp.oauth.refresh_token_store',
            'mcp.oauth.access_token_issuer',
            'mcp.oauth.token_validator',
            'mcp.oauth.code_issuer',
            'mcp.oauth.token_granter',
            'mcp.oauth.client_registrar',
            'mcp.oauth.authorization_server_metadata',
            'mcp.oauth.protected_resource_metadata',
            'mcp.oauth.authorize_middleware',
            'mcp.oauth.token_middleware',
            'mcp.oauth.controller',
        ] as $serviceId) {
            $this->assertTrue($container->hasDefinition($serviceId), \sprintf('Service "%s" should be registered', $serviceId));
        }

        $this->assertSame(OAuthController::class, $container->getDefinition('mcp.oauth.controller')->getClass());
        $this->assertTrue($container->getDefinition('mcp.oauth.controller')->isPublic());

        // Defaults: security-based resolver + auto-approve consent + the authenticator.
        $this->assertSame(SecurityResourceOwnerResolver::class, $container->getDefinition('mcp.oauth.resource_owner_resolver')->getClass());
        $this->assertSame('Mcp\\Server\\Transport\\Http\\OAuth\\AutoApproveConsent', $container->getDefinition('mcp.oauth.consent')->getClass());
        $this->assertTrue($container->hasDefinition(AccessTokenAuthenticator::class));

        // Token granter is bound to the resource + configured TTLs.
        $granterArgs = $container->getDefinition('mcp.oauth.token_granter')->getArguments();
        $this->assertSame('https://mcp.example.com/_mcp', $granterArgs[4]);
        $this->assertSame(3600, $granterArgs[5]);
        $this->assertSame(1209600, $granterArgs[6]);
    }

    public function testOAuthRegistersRoutesIncludingWellKnown(): void
    {
        $container = $this->buildContainer($this->enabledConfig());

        $routes = $container->getDefinition('mcp.server.route_loader')->getArgument(2);
        $names = array_column($routes, 'name');

        $this->assertContains('mcp_oauth_authorize', $names);
        $this->assertContains('mcp_oauth_token', $names);
        $this->assertContains('mcp_oauth_register', $names);
        $this->assertContains('mcp_oauth_as_metadata', $names);
        $this->assertContains('mcp_oauth_prm', $names);
        $this->assertContains('mcp_oauth_jwks', $names);
    }

    public function testClientRegistrationCanBeDisabled(): void
    {
        $config = $this->enabledConfig();
        $config['mcp']['oauth']['client_registration'] = false;

        $container = $this->buildContainer($config);
        $names = array_column($container->getDefinition('mcp.server.route_loader')->getArgument(2), 'name');

        $this->assertNotContains('mcp_oauth_register', $names);
    }

    public function testCustomResolverAndConsentBecomeAliases(): void
    {
        $config = $this->enabledConfig();
        $config['mcp']['oauth']['resource_owner_resolver'] = 'app.my_resolver';
        $config['mcp']['oauth']['consent'] = 'app.my_consent';

        $container = $this->buildContainer($config);

        $this->assertTrue($container->hasAlias('mcp.oauth.resource_owner_resolver'));
        $this->assertSame('app.my_resolver', (string) $container->getAlias('mcp.oauth.resource_owner_resolver'));
        $this->assertSame('app.my_consent', (string) $container->getAlias('mcp.oauth.consent'));
    }

    public function testEnablingWithoutIssuerThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->buildContainer([
            'mcp' => [
                'client_transports' => ['http' => true],
                'oauth' => ['enabled' => true, 'signing_key' => ['private_key' => '/tmp/key.pem']],
            ],
        ]);
    }

    public function testEnablingWithoutSigningKeyThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->buildContainer([
            'mcp' => [
                'client_transports' => ['http' => true],
                'oauth' => ['enabled' => true, 'issuer' => 'https://mcp.example.com'],
            ],
        ]);
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
                    'signing_key' => ['private_key' => '/tmp/key.pem'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function buildContainer(array $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setParameter('kernel.project_dir', '/path/to/project');

        $extension = (new McpBundle())->getContainerExtension();
        $extension->load($configuration, $container);

        return $container;
    }
}
