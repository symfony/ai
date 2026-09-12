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
use Mcp\Schema\Tool as McpTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ConnectionException;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Bridge\Mcp\McpToolbox;
use Symfony\AI\Agent\Bridge\Mcp\Tests\Double\StaticToolset;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class McpToolboxTest extends TestCase
{
    public function testAdvertisesTheRemoteToolsWithPrefixedNames()
    {
        $toolbox = new McpToolbox(new StaticToolset(tools: [
            $this->remoteTool('echo', ['msg' => ['type' => 'string']], 'Echoes a message back.'),
            $this->remoteTool('sum', ['a' => ['type' => 'integer']]),
        ]));

        $tools = $toolbox->getTools();

        $this->assertCount(2, $tools);
        $this->assertInstanceOf(Tool::class, $tools[0]);
        $this->assertSame('demo_echo', $tools[0]->getName());
        $this->assertSame('echo', $tools[0]->getReference()->getMethod());
        $this->assertSame(McpToolbox::class, $tools[0]->getReference()->getClass());
        $this->assertSame('Echoes a message back.', $tools[0]->getDescription());
        $this->assertSame(['type' => 'object', 'properties' => ['msg' => ['type' => 'string']], 'required' => ['msg']], $tools[0]->getParameters());

        $this->assertSame('demo_sum', $tools[1]->getName());
        $this->assertSame('', $tools[1]->getDescription());
    }

    public function testExplicitPrefixWinsOverTheToolsetName()
    {
        $toolbox = new McpToolbox(new StaticToolset(tools: [$this->remoteTool('echo')]), 'fs.');

        $this->assertSame('fs.echo', $toolbox->getTools()[0]->getName());
    }

    public function testToolWithoutArgumentsHasNoParameters()
    {
        $toolset = new StaticToolset(tools: [
            new McpTool(name: 'ping', title: null, inputSchema: ['type' => 'object', 'properties' => new \stdClass(), 'required' => []], description: null, annotations: null),
        ]);

        // An empty "properties" object would reach the platform as "[]", which is not a JSON object.
        $this->assertNull((new McpToolbox($toolset))->getTools()[0]->getParameters());
    }

    public function testRemoteToolsAreListedOnlyOnce()
    {
        $toolset = new StaticToolset(tools: [$this->remoteTool('echo')]);
        $toolbox = new McpToolbox($toolset);

        $toolbox->getTools();
        $toolbox->getTools();

        $this->assertSame(1, $toolset->listCalls);
    }

    public function testExecuteForwardsTheRemoteToolNameAndArguments()
    {
        $toolset = $this->toolset('echo', new CallToolResult([new TextContent('echoed: hello')]));

        $result = (new McpToolbox($toolset))->execute(new ToolCall('call_1', 'demo_echo', ['msg' => 'hello']));

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertSame('echoed: hello', $result->getResult());
        $this->assertSame([['name' => 'echo', 'arguments' => ['msg' => 'hello']]], $toolset->calls);
    }

    public function testExecuteJoinsMultipleTextChunks()
    {
        $toolset = $this->toolset('read', new CallToolResult([new TextContent('first'), new TextContent('second')]));

        $this->assertSame("first\nsecond", (new McpToolbox($toolset))->execute(new ToolCall('call_2', 'demo_read'))->getResult());
    }

    public function testExecuteKeepsNonTextContentAlongsideTheRenderedText()
    {
        $image = new ImageContent('YmluYXJ5', 'image/png');
        $toolset = $this->toolset('screenshot', new CallToolResult([new TextContent('taken'), $image]));

        $result = (new McpToolbox($toolset))->execute(new ToolCall('call_3', 'demo_screenshot'))->getResult();

        $this->assertSame('taken', $result['text']);
        $this->assertSame($image, $result['content'][1]);
    }

    public function testExecuteReturnsStructuredContentWhenProvided()
    {
        $toolset = $this->toolset('calc', new CallToolResult([new TextContent('ignored')], false, ['answer' => 42]));

        $this->assertSame(['answer' => 42], (new McpToolbox($toolset))->execute(new ToolCall('call_4', 'demo_calc'))->getResult());
    }

    public function testExecuteKeepsNonTextContentAlongsideStructuredContent()
    {
        $image = new ImageContent('YmluYXJ5', 'image/png');
        $toolset = $this->toolset('chart', new CallToolResult([new TextContent('{"total":3}'), $image], false, ['total' => 3]));

        $result = (new McpToolbox($toolset))->execute(new ToolCall('call_10', 'demo_chart'))->getResult();

        $this->assertSame(['total' => 3], $result['structuredContent']);
        $this->assertSame([$image], $result['content']);
    }

    public function testExecuteFallsBackToTheTextWhenStructuredContentIsEmpty()
    {
        // A client decodes the `{}` such a server sends into an empty array.
        $toolset = $this->toolset('search', new CallToolResult([new TextContent('no matches')], false, []));

        $this->assertSame('no matches', (new McpToolbox($toolset))->execute(new ToolCall('call_11', 'demo_search'))->getResult());
    }

    public function testExecuteWrapsServerSideErrorAsToolCallException()
    {
        $toolset = $this->toolset('oops', new CallToolResult([new TextContent('boom')], true));

        try {
            (new McpToolbox($toolset))->execute(new ToolCall('call_5', 'demo_oops'));
            $this->fail('Expected a ToolExecutionException.');
        } catch (ToolExecutionException $e) {
            $this->assertInstanceOf(ToolCallException::class, $e->getPrevious());
            $this->assertSame('Tool "oops" on MCP server "demo" returned an error: boom', $e->getPrevious()->getMessage());
        }
    }

    public function testExecuteReportsStructuredErrorPayloadAsJson()
    {
        $toolset = $this->toolset('oops', new CallToolResult([], true, ['code' => 'E_NOPE']));

        try {
            (new McpToolbox($toolset))->execute(new ToolCall('call_6', 'demo_oops'));
            $this->fail('Expected a ToolExecutionException.');
        } catch (ToolExecutionException $e) {
            $this->assertSame('Tool "oops" on MCP server "demo" returned an error: {"code":"E_NOPE"}', $e->getPrevious()->getMessage());
        }
    }

    public function testExecuteThrowsForAToolTheServerDoesNotAdvertise()
    {
        $this->expectException(ToolNotFoundException::class);

        (new McpToolbox(new StaticToolset()))->execute(new ToolCall('call_7', 'demo_unknown'));
    }

    public function testExecuteDispatchesTheToolCallEvents()
    {
        $dispatcher = new EventDispatcher();
        $succeeded = null;
        $dispatcher->addListener(ToolCallSucceeded::class, static function (ToolCallSucceeded $event) use (&$succeeded): void {
            $succeeded = $event;
        });

        $toolset = $this->toolset('echo', new CallToolResult([new TextContent('hi')]));
        $toolbox = new McpToolbox($toolset, eventDispatcher: $dispatcher);

        $toolbox->execute(new ToolCall('call_8', 'demo_echo', ['msg' => 'hi']));

        $this->assertInstanceOf(ToolCallSucceeded::class, $succeeded);
        $this->assertSame($toolbox, $succeeded->getTool());
        $this->assertSame('demo_echo', $succeeded->getDefinition()->getName());
        $this->assertSame(['msg' => 'hi'], $succeeded->getArguments());
    }

    public function testADeniedToolCallIsNotForwardedToTheServer()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallRequested::class, static function (ToolCallRequested $event): void {
            $event->deny('not allowed');
        });

        $toolset = $this->toolset('echo', new CallToolResult([new TextContent('hi')]));

        $result = (new McpToolbox($toolset, eventDispatcher: $dispatcher))->execute(new ToolCall('call_9', 'demo_echo'));

        $this->assertSame('not allowed', $result->getResult());
        $this->assertSame([], $toolset->calls);
    }

    public function testAnUnreachableServerAdvertisesNoToolsInsteadOfFailing()
    {
        $toolbox = new McpToolbox(new StaticToolset(listFailure: $this->unreachable()));

        $this->assertSame([], $toolbox->getTools());
    }

    public function testAnUnreachableServerIsLoggedAsAnError()
    {
        $failure = $this->unreachable();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to list the tools of MCP server "demo", it contributes none to this run.',
                ['exception' => $failure],
            );

        (new McpToolbox(new StaticToolset(listFailure: $failure), logger: $logger))->getTools();
    }

    public function testAFailedListingIsNotCached()
    {
        $toolset = new StaticToolset(tools: [$this->remoteTool('echo')], listFailure: $this->unreachable());
        $toolbox = new McpToolbox($toolset);

        $this->assertSame([], $toolbox->getTools());

        $toolset->listFailure = null;
        $tools = $toolbox->getTools();

        $this->assertCount(1, $tools);
        $this->assertSame('demo_echo', $tools[0]->getName());
        $this->assertSame(2, $toolset->listCalls);
    }

    public function testCallingAToolOfAnUnreachableServerReportsItAsNotFound()
    {
        $toolbox = new McpToolbox(new StaticToolset(listFailure: $this->unreachable()));

        $this->expectException(ToolNotFoundException::class);

        $toolbox->execute(new ToolCall('call_1', 'demo_echo'));
    }

    private function unreachable(): ConnectionException
    {
        return ConnectionException::failed('demo', new \RuntimeException('Connection refused'));
    }

    private function toolset(string $name, CallToolResult $result): StaticToolset
    {
        return new StaticToolset(tools: [$this->remoteTool($name)], results: [$name => $result]);
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     */
    private function remoteTool(string $name, array $properties = [], ?string $description = null): McpTool
    {
        return new McpTool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties)],
            description: $description,
            annotations: null,
        );
    }
}
