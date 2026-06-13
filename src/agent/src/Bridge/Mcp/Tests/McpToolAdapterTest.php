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

use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Bridge\Mcp\McpToolAdapter;
use Symfony\AI\Agent\Bridge\Mcp\Tests\Double\StaticToolset;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class McpToolAdapterTest extends TestCase
{
    public function testExecuteForwardsTheRemoteToolNameAndArguments()
    {
        $toolset = new StaticToolset(results: [
            'echo' => new CallToolResult([new TextContent('echoed: hello')]),
        ]);
        $adapter = new McpToolAdapter($toolset);

        $result = $adapter->execute($this->metadata('echo'), new ToolCall('call_1', 'demo_echo', ['msg' => 'hello']));

        $this->assertSame('echoed: hello', $result);
        $this->assertSame([['name' => 'echo', 'arguments' => ['msg' => 'hello']]], $toolset->calls);
    }

    public function testExecuteJoinsMultipleTextChunks()
    {
        $adapter = new McpToolAdapter(new StaticToolset(results: [
            'read' => new CallToolResult([new TextContent('first'), new TextContent('second')]),
        ]));

        $this->assertSame("first\nsecond", $adapter->execute($this->metadata('read'), new ToolCall('call_2', 'demo_read')));
    }

    public function testExecuteKeepsNonTextContentAlongsideTheRenderedText()
    {
        $image = new ImageContent('YmluYXJ5', 'image/png');
        $adapter = new McpToolAdapter(new StaticToolset(results: [
            'screenshot' => new CallToolResult([new TextContent('taken'), $image]),
        ]));

        $result = $adapter->execute($this->metadata('screenshot'), new ToolCall('call_3', 'demo_screenshot'));

        $this->assertSame('taken', $result['text']);
        $this->assertSame($image, $result['content'][1]);
    }

    public function testExecuteReturnsStructuredContentWhenProvided()
    {
        $adapter = new McpToolAdapter(new StaticToolset(results: [
            'calc' => new CallToolResult([new TextContent('ignored')], false, ['answer' => 42]),
        ]));

        $this->assertSame(['answer' => 42], $adapter->execute($this->metadata('calc'), new ToolCall('call_4', 'demo_calc')));
    }

    public function testExecuteWrapsServerSideErrorAsToolCallException()
    {
        $adapter = new McpToolAdapter(new StaticToolset(results: [
            'oops' => new CallToolResult([new TextContent('boom')], true),
        ]));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Tool "oops" on MCP server "demo" returned an error: boom');

        $adapter->execute($this->metadata('oops'), new ToolCall('call_5', 'demo_oops'));
    }

    public function testExecuteReportsStructuredErrorPayloadAsJson()
    {
        $adapter = new McpToolAdapter(new StaticToolset(results: [
            'oops' => new CallToolResult([], true, ['code' => 'E_NOPE']),
        ]));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Tool "oops" on MCP server "demo" returned an error: {"code":"E_NOPE"}');

        $adapter->execute($this->metadata('oops'), new ToolCall('call_6', 'demo_oops'));
    }

    public function testPrefixDefaultsToTheToolsetName()
    {
        $adapter = new McpToolAdapter(new StaticToolset());

        $this->assertSame('demo_', $adapter->getPrefix());
    }

    public function testExplicitPrefixWins()
    {
        $adapter = new McpToolAdapter(new StaticToolset(), 'fs.');

        $this->assertSame('fs.', $adapter->getPrefix());
    }

    private function metadata(string $remoteName): Tool
    {
        return new Tool(new ExecutionReference(McpToolAdapter::class, $remoteName), 'demo_'.$remoteName, '');
    }
}
