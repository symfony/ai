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
use Symfony\AI\McpBundle\Client\ServerConnection;
use Symfony\AI\McpBundle\Exception\ConnectionException;
use Symfony\AI\McpBundle\Exception\RemoteCallException;
use Symfony\AI\McpBundle\Tests\Double\InMemoryTransport;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ServerConnectionTest extends TestCase
{
    public function testDoesNotConnectOnConstruction()
    {
        $transport = new InMemoryTransport();
        $connection = $this->createConnection($transport);

        $this->assertSame(0, $transport->connectCount);
        $this->assertFalse($connection->isConnected());
    }

    public function testConnectsOnFirstUseAndOnlyOnce()
    {
        $transport = new InMemoryTransport(['tools/list' => [['tools' => []]]]);
        $connection = $this->createConnection($transport);

        $connection->listTools();
        $connection->listTools();
        $connection->listTools();

        $this->assertSame(1, $transport->connectCount);
        $this->assertTrue($connection->isConnected());
    }

    public function testCallToolForwardsNameAndArguments()
    {
        $transport = new InMemoryTransport(['tools/call' => [['content' => [['type' => 'text', 'text' => 'ok']]]]]);
        $connection = $this->createConnection($transport);

        $connection->callTool('publish', ['id' => 42]);

        $call = end($transport->sent);
        $this->assertSame('tools/call', $call['method']);
        $this->assertSame('publish', $call['params']['name']);
        $this->assertSame(['id' => 42], $call['params']['arguments']);
    }

    public function testGetToolsFollowsPaginationToItsEnd()
    {
        $transport = new InMemoryTransport(['tools/list' => [
            ['tools' => [$this->tool('first')], 'nextCursor' => 'page-2'],
            ['tools' => [$this->tool('second')]],
        ]]);

        $tools = $this->createConnection($transport)->getTools();

        $this->assertCount(2, $tools);
        $this->assertSame(['first', 'second'], array_map(static fn ($tool): string => $tool->name, $tools));
    }

    public function testServerInfoAndInstructionsComeFromTheHandshake()
    {
        $connection = $this->createConnection(new InMemoryTransport());

        $this->assertSame('test-server', $connection->getServerInfo()?->name);
        $this->assertSame('Be nice.', $connection->getInstructions());
    }

    public function testDisconnectIsIdempotent()
    {
        $transport = new InMemoryTransport(['tools/list' => [['tools' => []]]]);
        $connection = $this->createConnection($transport);

        $connection->listTools();
        $connection->disconnect();
        $connection->disconnect();

        $this->assertSame(1, $transport->closeCount);
        $this->assertFalse($connection->isConnected());
    }

    public function testDisconnectingWithoutHavingConnectedDoesNothing()
    {
        $transport = new InMemoryTransport();

        $this->createConnection($transport)->disconnect();

        $this->assertSame(0, $transport->closeCount);
    }

    public function testReconnectsAfterDisconnect()
    {
        $transport = new InMemoryTransport(['tools/list' => [['tools' => []]]]);
        $connection = $this->createConnection($transport);

        $connection->listTools();
        $connection->disconnect();
        $connection->listTools();

        $this->assertSame(2, $transport->connectCount);
        $this->assertTrue($connection->isConnected());
    }

    public function testConnectionFailureIsTranslatedAndLeavesTheConnectionRetryable()
    {
        $transport = new InMemoryTransport(failOnConnect: true);
        $connection = $this->createConnection($transport);

        try {
            $connection->listTools();
            $this->fail('Expected a ConnectionException.');
        } catch (ConnectionException $e) {
            $this->assertSame('Failed to connect MCP client "research" to server "github": Initialization failed: the double refuses to connect.', $e->getMessage());
        }

        $this->assertFalse($connection->isConnected());

        // A failed connect must not latch: the next call tries again.
        $this->expectException(ConnectionException::class);
        $connection->listTools();
    }

    public function testRequestFailureIsTranslatedAndNamesTheOperation()
    {
        $transport = new InMemoryTransport(failures: ['tools/call' => 'tool exploded']);
        $connection = $this->createConnection($transport);

        $this->expectException(RemoteCallException::class);
        $this->expectExceptionMessage('The "tools/call" request of MCP client "research" to server "github" failed: tool exploded');

        $connection->callTool('publish');
    }

    public function testResetSwallowsDisconnectFailures()
    {
        $client = $this->createMock(Client::class);
        $client->method('isConnected')->willReturn(true);
        $client->method('disconnect')->willThrowException(new \Mcp\Exception\ConnectionException('already gone'));

        $transport = new InMemoryTransport();
        $connection = new ServerConnection('research', 'github', $client, $transport);

        // Opens the connection through the real handshake path before resetting.
        $connection->getServerInfo();
        $connection->reset();

        $this->assertFalse($connection->isConnected());
    }

    public function testNamesAreExposed()
    {
        $connection = $this->createConnection(new InMemoryTransport());

        $this->assertSame('github', $connection->getName());
        $this->assertSame('research', $connection->getClientName());
    }

    private function createConnection(InMemoryTransport $transport): ServerConnection
    {
        return new ServerConnection('research', 'github', Client::builder()->build(), $transport);
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(string $name): array
    {
        return ['name' => $name, 'inputSchema' => ['type' => 'object']];
    }
}
