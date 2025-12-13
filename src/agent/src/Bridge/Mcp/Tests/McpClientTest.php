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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\McpClient;
use Symfony\AI\Agent\Bridge\Mcp\Transport\TransportInterface;

/**
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class McpClientTest extends TestCase
{
    public function testInitialize()
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('connect');
        $transport->expects($this->once())->method('request')->willReturn([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'serverInfo' => [
                    'name' => 'test-server',
                    'version' => '1.0.0',
                ],
            ],
        ]);
        $transport->expects($this->once())->method('notify');

        $client = new McpClient($transport);
        $result = $client->initialize();

        $this->assertSame('test-server', $result->serverInfo->name);
        $this->assertSame('1.0.0', $result->serverInfo->version);
    }

    public function testListTools()
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('request')->willReturnOnConsecutiveCalls(
            // initialize response
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [],
                    'serverInfo' => ['name' => 'test', 'version' => '1.0'],
                ],
            ],
            // tools/list response
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'test_tool',
                            'description' => 'A test tool',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'query' => ['type' => 'string'],
                                ],
                                'required' => ['query'],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $client = new McpClient($transport);
        $tools = $client->listTools();

        $this->assertCount(1, $tools);
        $this->assertSame('test_tool', $tools[0]->name);
        $this->assertSame('A test tool', $tools[0]->description);
    }

    public function testCallTool()
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('request')->willReturnOnConsecutiveCalls(
            // initialize response
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [],
                    'serverInfo' => ['name' => 'test', 'version' => '1.0'],
                ],
            ],
            // tools/call response
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'content' => [
                        ['type' => 'text', 'text' => 'Tool result'],
                    ],
                ],
            ]
        );

        $client = new McpClient($transport);
        $result = $client->callTool('test_tool', ['query' => 'test']);

        $this->assertCount(1, $result->content);
    }
}
