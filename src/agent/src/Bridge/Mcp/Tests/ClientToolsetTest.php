<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp\Tests;

use Mcp\Client;
use Mcp\Client\Configuration;
use Mcp\Client\Protocol;
use Mcp\Client\Transport\TransportInterface;
use Mcp\Exception\ConnectionException as McpConnectionException;
use Mcp\Exception\RequestException;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Implementation;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool as McpTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\ClientToolset;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ConnectionException;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;

final class ClientToolsetTest extends TestCase
{
    public function testConnectIsCalledOnceAcrossMultipleOperations()
    {
        $client = $this->createClient();
        $client->expects($this->once())->method('connect');
        $client->method('listTools')->willReturn(new ListToolsResult([]));

        $toolset = new ClientToolset('demo', $client, $this->createMock(TransportInterface::class));

        $toolset->getTools();
        $toolset->getTools();
    }

    public function testGetToolsPaginatesAcrossCursors()
    {
        $page1 = new ListToolsResult([$this->makeTool('a')], 'cursor-1');
        $page2 = new ListToolsResult([$this->makeTool('b'), $this->makeTool('c')], null);

        $client = $this->createClient();
        $client->method('connect');
        $client->expects($this->exactly(2))
            ->method('listTools')
            ->willReturnOnConsecutiveCalls($page1, $page2);

        $toolset = new ClientToolset('demo', $client, $this->createMock(TransportInterface::class));
        $tools = $toolset->getTools();

        $this->assertCount(3, $tools);
        $this->assertSame(['a', 'b', 'c'], array_map(static fn (McpTool $t) => $t->name, $tools));
    }

    public function testCallToolForwardsToTheClient()
    {
        $client = $this->createClient();
        $client->method('connect');
        $client->expects($this->once())
            ->method('callTool')
            ->with('echo', ['msg' => 'hello'])
            ->willReturn($expected = new CallToolResult([new TextContent('echoed: hello')]));

        $toolset = new ClientToolset('demo', $client, $this->createMock(TransportInterface::class));

        $this->assertSame($expected, $toolset->callTool('echo', ['msg' => 'hello']));
    }

    public function testConnectFailureWrappedAsBridgeException()
    {
        $client = $this->createClient();
        $client->method('connect')->willThrowException(new McpConnectionException('handshake failed'));

        $toolset = new ClientToolset('demo', $client, $this->createMock(TransportInterface::class));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to connect to MCP server "demo": handshake failed');

        $toolset->getTools();
    }

    public function testListFailureWrappedAsBridgeException()
    {
        $client = $this->createClient();
        $client->method('connect');
        $client->method('listTools')->willThrowException(new RequestException('rpc fail'));

        $toolset = new ClientToolset('demo', $client, $this->createMock(TransportInterface::class));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to list tools on MCP server "demo": rpc fail');

        $toolset->getTools();
    }

    public function testCallFailureWrappedAsBridgeException()
    {
        $client = $this->createClient();
        $client->method('connect');
        $client->method('callTool')->willThrowException(new RequestException('rpc fail'));

        $toolset = new ClientToolset('demo', $client, $this->createMock(TransportInterface::class));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Tool "die" on MCP server "demo" failed: rpc fail');

        $toolset->callTool('die');
    }

    public function testDisconnectIsIdempotentAndOnlyClosesAnOpenConnection()
    {
        $client = $this->createClient();
        $client->method('connect');
        $client->method('listTools')->willReturn(new ListToolsResult([]));
        $client->expects($this->once())->method('disconnect');

        $toolset = new ClientToolset('demo', $client, $this->createMock(TransportInterface::class));

        $toolset->disconnect();
        $toolset->getTools();
        $toolset->disconnect();
        $toolset->disconnect();
    }

    public function testNameIsTheRemoteServerName()
    {
        $toolset = new ClientToolset('filesystem', $this->createClient(), $this->createMock(TransportInterface::class));

        $this->assertSame('filesystem', $toolset->getName());
    }

    private function makeTool(string $name): McpTool
    {
        return new McpTool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: null,
            annotations: null,
        );
    }

    /**
     * @return Client&MockObject
     */
    private function createClient(): Client
    {
        return $this->getMockBuilder(Client::class)
            ->setConstructorArgs([
                new Protocol(),
                new Configuration(new Implementation('test', '1.0'), new ClientCapabilities()),
            ])
            ->onlyMethods(['connect', 'listTools', 'callTool', 'disconnect'])
            ->getMock();
    }
}
