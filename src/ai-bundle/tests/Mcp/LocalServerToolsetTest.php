<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Mcp;

use Mcp\Exception\ToolCallException as McpToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Bridge\Mcp\McpToolbox;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;
use Symfony\AI\AiBundle\Mcp\LocalServerToolset;
use Symfony\AI\Platform\Result\ToolCall;

final class LocalServerToolsetTest extends TestCase
{
    public function testListsTheToolsTheServerExposes()
    {
        $tools = $this->toolset()->getTools();

        $this->assertSame(['greet', 'fail'], array_map(static fn ($tool) => $tool->name, $tools));
        $this->assertSame(['name'], $tools[0]->inputSchema['required']);
    }

    public function testCallsAToolInProcess()
    {
        $result = $this->toolset()->callTool('greet', ['name' => 'Ada']);

        $this->assertFalse($result->isError);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);
        $this->assertSame('Hello Ada', $result->content[0]->text);
    }

    public function testAToolErrorIsAnErrorResult()
    {
        $result = $this->toolset()->callTool('fail');

        $this->assertTrue($result->isError);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);
        $this->assertSame('No such greeting.', $result->content[0]->text);
    }

    public function testInvalidArgumentsFailTheCall()
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Tool "greet" on MCP server "local" failed:');

        $this->toolset()->callTool('greet', []);
    }

    public function testAnUnknownToolFailsTheCall()
    {
        $this->expectException(ToolCallException::class);

        $this->toolset()->callTool('missing');
    }

    public function testServesAnAgentToolbox()
    {
        $toolbox = new McpToolbox($this->toolset());

        $this->assertSame(['local_greet', 'local_fail'], array_map(static fn ($tool) => $tool->getName(), $toolbox->getTools()));
        $this->assertSame('Hello Grace', $toolbox->execute(new ToolCall('call_1', 'local_greet', ['name' => 'Grace']))->getResult());

        $this->expectException(ToolExecutionExceptionInterface::class);
        $toolbox->execute(new ToolCall('call_2', 'local_fail'));
    }

    private function toolset(): LocalServerToolset
    {
        $builder = Server::builder()
            ->setServerInfo('local', '1.0.0')
            ->addTool(static fn (string $name): string => 'Hello '.$name, 'greet', description: 'Greets someone.')
            ->addTool(static fn (): string => throw new McpToolCallException('No such greeting.'), 'fail', description: 'Always fails.');

        return new LocalServerToolset('local', $builder);
    }
}
