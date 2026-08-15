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

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr18ClientDiscovery;
use Mcp\Client as McpSdkClient;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Client\Transport\StdioTransport;
use Mcp\Schema\ClientCapabilities;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Client\McpClient;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\AI\McpBundle\Client\ServerConnection;
use Symfony\AI\McpBundle\Client\TransportFactory;
use Symfony\AI\McpBundle\Exception\RuntimeException;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpBundleClientTest extends TestCase
{
    public function testNoClientsRegistersNothing()
    {
        $container = $this->buildContainer([]);

        $this->assertFalse($container->hasDefinition('mcp.client.locator'));
        $this->assertFalse($container->hasDefinition('mcp.client.debug_command'));
    }

    public function testRegistersTheFullGraphPerConnection()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => [
                'github' => ['transport' => 'http', 'url' => 'https://example.com/mcp'],
                'filesystem' => ['transport' => 'stdio', 'command' => ['npx', '-y', 'server-filesystem', '/tmp']],
            ]],
        ]));

        foreach (['github', 'filesystem'] as $server) {
            $id = 'mcp.client.research.server.'.$server;
            $this->assertTrue($container->hasDefinition($id));
            $this->assertTrue($container->hasDefinition($id.'.client'));
            $this->assertTrue($container->hasDefinition($id.'.builder'));
            $this->assertTrue($container->hasDefinition($id.'.transport'));

            $connection = $container->getDefinition($id);
            $this->assertSame(ServerConnection::class, $connection->getClass());
            $this->assertSame([['client' => 'research', 'server' => $server]], $connection->getTag('mcp.client.connection'));
            // Stdio connections own a child process, so a worker must drop them between messages.
            $this->assertSame([['method' => 'reset']], $connection->getTag('kernel.reset'));
        }

        $aggregate = $container->getDefinition('mcp.client.research');
        $this->assertSame(McpClient::class, $aggregate->getClass());
        $this->assertSame([['name' => 'research']], $aggregate->getTag('mcp.client'));

        $locator = $container->getDefinition('mcp.client.research.locator');
        $this->assertSame(['github', 'filesystem'], array_keys($locator->getArgument(0)));

        $this->assertTrue($container->hasDefinition('mcp.client.debug_command'));
    }

    public function testStdioTransportGoesThroughTheFactory()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['filesystem' => [
                'transport' => 'stdio',
                'command' => ['npx', '-y', 'server-filesystem', '/tmp'],
                'cwd' => '/srv',
                'env' => ['TOKEN' => 'secret'],
                'inherit_env' => false,
            ]]],
        ]));

        $transport = $container->getDefinition('mcp.client.research.server.filesystem.transport');

        $this->assertSame(StdioTransport::class, $transport->getClass());
        $this->assertSame([TransportFactory::class, 'stdio'], $transport->getFactory());
        $this->assertSame(['npx', '-y', 'server-filesystem', '/tmp'], $transport->getArgument(0));
        $this->assertSame('/srv', $transport->getArgument(1));
        $this->assertSame(['TOKEN' => 'secret'], $transport->getArgument(2));
        $this->assertFalse($transport->getArgument(3));
        $this->assertNull($transport->getArgument(4));
    }

    public function testHttpTransportFallsBackToTheFrameworkClient()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['github' => [
                'transport' => 'http',
                'url' => 'https://example.com/mcp',
                'headers' => ['Authorization' => 'Bearer t'],
            ]]],
        ]));

        $transport = $container->getDefinition('mcp.client.research.server.github.transport');

        $this->assertSame(HttpTransport::class, $transport->getClass());
        $this->assertSame([TransportFactory::class, 'http'], $transport->getFactory());
        $this->assertSame('https://example.com/mcp', $transport->getArgument(0));
        $this->assertSame(['Authorization' => 'Bearer t'], $transport->getArgument(1));

        $httpClient = $transport->getArgument(2);
        $this->assertInstanceOf(Reference::class, $httpClient);
        $this->assertSame('psr18.http_client', (string) $httpClient);
        // Degrades to the SDK's own discovery when symfony/http-client is absent.
        $this->assertSame(ContainerBuilder::NULL_ON_INVALID_REFERENCE, $httpClient->getInvalidBehavior());
    }

    public function testCustomHttpClientIsUsed()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp', 'http_client' => 'app.psr18']]],
        ]));

        $this->assertSame('app.psr18', (string) $container->getDefinition('mcp.client.research.server.github.transport')->getArgument(2));
    }

    public function testClientInfoDefaultsToTheConfigurationKey()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']]],
        ]));

        $this->assertSame(['research', '0.0.1', null], $this->callsNamed($container, 'research', 'github', 'setClientInfo')[0][1]);
    }

    public function testTimeoutsInheritFromTheClientAndCanBeOverriddenPerServer()
    {
        $container = $this->buildContainer($this->config([
            'research' => [
                'request_timeout' => 60,
                'servers' => [
                    'github' => ['transport' => 'http', 'url' => 'https://example.com/mcp'],
                    'slow' => ['transport' => 'http', 'url' => 'https://example.com/slow', 'request_timeout' => 300],
                ],
            ],
        ]));

        $this->assertSame([60], $this->callsNamed($container, 'research', 'github', 'setRequestTimeout')[0][1]);
        $this->assertSame([300], $this->callsNamed($container, 'research', 'slow', 'setRequestTimeout')[0][1]);
    }

    public function testCapabilitiesAreDerivedFromTheConfiguredHandlers()
    {
        $container = $this->buildContainer($this->config([
            'plain' => ['servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']]],
            'rich' => [
                'sampling' => 'app.sampling',
                'elicitation' => 'app.elicitation',
                'capabilities' => ['roots' => true],
                'servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']],
            ],
        ]));

        $plain = $this->callsNamed($container, 'plain', 'github', 'setCapabilities')[0][1][0];
        $this->assertInstanceOf(Definition::class, $plain);
        $this->assertSame(ClientCapabilities::class, $plain->getClass());
        // Advertising a capability without a handler makes the server get "method not found".
        $this->assertSame([false, false, false, false], $plain->getArguments());

        $rich = $this->callsNamed($container, 'rich', 'github', 'setCapabilities')[0][1][0];
        $this->assertSame([true, false, true, true], $rich->getArguments());
        $this->assertCount(2, $this->callsNamed($container, 'rich', 'github', 'addRequestHandler'));
    }

    public function testServerLogsAreForwardedByDefault()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']]],
            'quiet' => ['forward_server_logs' => false, 'servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']]],
        ]));

        $this->assertCount(1, $this->callsNamed($container, 'research', 'github', 'addNotificationHandler'));
        $this->assertCount(0, $this->callsNamed($container, 'quiet', 'github', 'addNotificationHandler'));
    }

    public function testTwoClientsSharingAServerNameGetSeparateConnections()
    {
        $server = ['transport' => 'http', 'url' => 'https://example.com/mcp'];
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['github' => $server, 'filesystem' => ['transport' => 'stdio', 'command' => ['npx']]]],
            'simple' => ['servers' => ['github' => $server]],
        ]));

        $this->assertTrue($container->hasDefinition('mcp.client.research.server.github'));
        $this->assertTrue($container->hasDefinition('mcp.client.simple.server.github'));
        $this->assertSame(['github', 'filesystem'], array_keys($container->getDefinition('mcp.client.research.locator')->getArgument(0)));
        $this->assertSame(['github'], array_keys($container->getDefinition('mcp.client.simple.locator')->getArgument(0)));
    }

    public function testClientIsAliasedForArgument()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']]],
        ]));

        $this->assertSame('mcp.client.research', (string) $container->getAlias(McpClientInterface::class.' $researchClient'));
        // A single client also answers a plain McpClientInterface type hint.
        $this->assertSame('mcp.client.research', (string) $container->getAlias(McpClientInterface::class));
    }

    public function testInterfaceIsNotAliasedWithSeveralClients()
    {
        $server = ['transport' => 'http', 'url' => 'https://example.com/mcp'];
        $container = $this->buildContainer($this->config([
            'research' => ['servers' => ['github' => $server]],
            'simple' => ['servers' => ['github' => $server]],
        ]));

        $this->assertFalse($container->hasAlias(McpClientInterface::class));
    }

    public function testProtocolVersionIsOptional()
    {
        $container = $this->buildContainer($this->config([
            'research' => ['protocol_version' => '2025-11-25', 'servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']]],
        ]));

        $this->assertCount(1, $this->callsNamed($container, 'research', 'github', 'setProtocolVersion'));
    }

    public function testContainerCompilesAndBuildsTheConnections()
    {
        $container = $this->buildContainer($this->config([
            'research' => [
                'protocol_version' => '2025-11-25',
                'servers' => [
                    'github' => ['transport' => 'http', 'url' => 'https://example.com/mcp'],
                    'filesystem' => ['transport' => 'stdio', 'command' => ['php', '-r', 'exit;']],
                ],
            ],
        ]));

        $container->getDefinition('mcp.client.research')->setPublic(true);
        $container->getDefinition('mcp.client.research.server.github.client')->setPublic(true);
        $container->register('logger', NullLogger::class);
        $container->compile();

        $client = $container->get('mcp.client.research');
        $this->assertInstanceOf(McpClientInterface::class, $client);
        $this->assertSame(['github', 'filesystem'], $client->getServerNames());
        $this->assertInstanceOf(McpSdkClient::class, $container->get('mcp.client.research.server.github.client'));

        // The stdio connection resolves without I/O; the HTTP one is left alone because no PSR-18
        // client is installed in this test environment (covered by testHttpWithoutPsr18ClientFails).
        $connection = $client->get('filesystem');
        $this->assertFalse($connection->isConnected());
    }

    public function testHttpWithoutPsr18ClientFailsWithAnActionableMessage()
    {
        try {
            Psr18ClientDiscovery::find();
            $this->markTestSkipped('A PSR-18 client is installed, so discovery succeeds and the fallback cannot be reached.');
        } catch (NotFoundException) {
            // No PSR-18 client: this is the case the message is written for.
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No PSR-18 HTTP client is available to reach the MCP server at "https://example.com/mcp".');

        TransportFactory::http('https://example.com/mcp', [], null, null, null, null, null);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function provideInvalidServerConfigurations(): iterable
    {
        yield 'stdio without command' => [
            ['transport' => 'stdio'],
            'A "command" must be configured for the "stdio" transport.',
        ];

        yield 'http without url' => [
            ['transport' => 'http'],
            'A "url" must be configured for the "http" transport.',
        ];

        yield 'stdio with http options' => [
            ['transport' => 'stdio', 'command' => ['npx'], 'url' => 'https://example.com'],
            'are only supported by the "http" transport',
        ];

        yield 'http with stdio options' => [
            ['transport' => 'http', 'url' => 'https://example.com', 'command' => ['npx']],
            'are only supported by the "stdio" transport',
        ];
    }

    /**
     * @param array<string, mixed> $server
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideInvalidServerConfigurations')]
    public function testInvalidServerConfigurationIsRejected(array $server, string $message)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->buildContainer($this->config(['research' => ['servers' => ['github' => $server]]]));
    }

    public function testClientWithoutServersIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->buildContainer(['mcp' => ['clients' => ['research' => []]]]);
    }

    public function testInvalidClientNameIsRejected()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('MCP client names must only contain letters, digits, underscores and hyphens');

        $this->buildContainer($this->config(['my.client' => ['servers' => ['github' => ['transport' => 'http', 'url' => 'https://example.com/mcp']]]]));
    }

    /**
     * @param array<string, array<string, mixed>> $clients
     *
     * @return array<string, mixed>
     */
    private function config(array $clients): array
    {
        return ['mcp' => ['clients' => $clients]];
    }

    /**
     * @return list<array{0: string, 1: array<int, mixed>}>
     */
    private function callsNamed(ContainerBuilder $container, string $client, string $server, string $method): array
    {
        return array_values(array_filter(
            $container->getDefinition(\sprintf('mcp.client.%s.server.%s.builder', $client, $server))->getMethodCalls(),
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
