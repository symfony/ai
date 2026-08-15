<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Client;

use Mcp\Client;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Client\McpClient;
use Symfony\AI\McpBundle\Client\ServerConnection;
use Symfony\AI\McpBundle\Exception\InvalidArgumentException;
use Symfony\AI\McpBundle\Tests\Double\InMemoryTransport;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpClientTest extends TestCase
{
    public function testExposesItsServers()
    {
        $client = $this->createClient(['github' => new InMemoryTransport(), 'filesystem' => new InMemoryTransport()]);

        $this->assertSame('research', $client->getName());
        $this->assertSame(['github', 'filesystem'], $client->getServerNames());
        $this->assertCount(2, $client);
        $this->assertTrue($client->has('github'));
        $this->assertFalse($client->has('gitlab'));
        $this->assertSame('github', $client->get('github')->getName());
    }

    public function testUnknownServerListsTheConfiguredOnes()
    {
        $client = $this->createClient(['github' => new InMemoryTransport()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The MCP client "research" has no server named "gitlab". Configured: "github".');

        $client->get('gitlab');
    }

    public function testIterationYieldsConnectionsWithoutConnectingThem()
    {
        $transports = ['github' => new InMemoryTransport(), 'filesystem' => new InMemoryTransport()];
        $client = $this->createClient($transports);

        $names = [];
        foreach ($client as $name => $connection) {
            $names[] = $name;
            $this->assertSame($name, $connection->getName());
        }

        $this->assertSame(['github', 'filesystem'], $names);
        $this->assertSame(0, $transports['github']->connectCount);
        $this->assertSame(0, $transports['filesystem']->connectCount);
    }

    public function testDisconnectClosesEveryOpenConnection()
    {
        $transports = ['github' => new InMemoryTransport(), 'filesystem' => new InMemoryTransport()];
        $client = $this->createClient($transports);

        $client->get('github')->getServerInfo();
        $client->disconnect();

        $this->assertSame(1, $transports['github']->closeCount);
        // Never used, so nothing to close.
        $this->assertSame(0, $transports['filesystem']->closeCount);
    }

    /**
     * @param array<string, InMemoryTransport> $transports
     */
    private function createClient(array $transports): McpClient
    {
        $connections = [];
        foreach ($transports as $name => $transport) {
            $connections[$name] = static fn (): ServerConnection => new ServerConnection('research', $name, Client::builder()->build(), $transport);
        }

        return new McpClient('research', new ServiceLocator($connections));
    }
}
