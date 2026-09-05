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

use Mcp\Schema\Tool as McpTool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Mcp\McpToolAdapter;
use Symfony\AI\Agent\Bridge\Mcp\McpToolFactory;
use Symfony\AI\Agent\Bridge\Mcp\Tests\Double\StaticToolset;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Platform\Tool\Tool;

final class McpToolFactoryTest extends TestCase
{
    public function testYieldsToolsWithPrefixedNames()
    {
        $toolset = new StaticToolset(tools: [
            new McpTool(
                name: 'echo',
                title: null,
                inputSchema: ['type' => 'object', 'properties' => ['msg' => ['type' => 'string']], 'required' => ['msg']],
                description: 'Echoes a message back.',
                annotations: null,
            ),
            new McpTool(
                name: 'sum',
                title: null,
                inputSchema: ['type' => 'object', 'properties' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'integer']], 'required' => ['a', 'b']],
                description: null,
                annotations: null,
            ),
        ]);

        $tools = iterator_to_array((new McpToolFactory())->getTool(new McpToolAdapter($toolset)), false);

        $this->assertCount(2, $tools);
        $this->assertInstanceOf(Tool::class, $tools[0]);
        $this->assertSame('demo_echo', $tools[0]->getName());
        $this->assertSame('echo', $tools[0]->getReference()->getMethod());
        $this->assertSame(McpToolAdapter::class, $tools[0]->getReference()->getClass());
        $this->assertSame('Echoes a message back.', $tools[0]->getDescription());
        $this->assertSame(['type' => 'object', 'properties' => ['msg' => ['type' => 'string']], 'required' => ['msg']], $tools[0]->getParameters());

        $this->assertSame('demo_sum', $tools[1]->getName());
        $this->assertSame('sum', $tools[1]->getReference()->getMethod());
        $this->assertSame('', $tools[1]->getDescription());
    }

    public function testExplicitPrefixIsUsedForToolNames()
    {
        $toolset = new StaticToolset(tools: [
            new McpTool(
                name: 'echo',
                title: null,
                inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
                description: null,
                annotations: null,
            ),
        ]);

        $tools = iterator_to_array((new McpToolFactory())->getTool(new McpToolAdapter($toolset, 'fs.')), false);

        $this->assertSame('fs.echo', $tools[0]->getName());
    }

    public function testThrowsOnUnsupportedReference()
    {
        $factory = new McpToolFactory();

        $this->expectException(ToolException::class);
        iterator_to_array($factory->getTool(new \stdClass()), false);
    }
}
