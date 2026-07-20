<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Profiler;

use Mcp\Capability\Registry;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Profiler\DataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DataCollectorTest extends TestCase
{
    public function testGetNameReturnsShortName()
    {
        $registry = new Registry(null, new NullLogger());
        $dataCollector = new DataCollector(Server::builder()->setRegistry($registry), $registry);

        $name = $dataCollector->getName();

        $this->assertSame('mcp', $name);
        // Verify it's a short name, not a class name
        $this->assertStringNotContainsString('\\', $name);
        $this->assertStringNotContainsString('DataCollector', $name);
    }

    public function testLateCollectBuildsServerToPopulateEmptyRegistry()
    {
        $registry = new Registry(null, new NullLogger());
        $builder = Server::builder()
            ->setRegistry($registry)
            ->addTool([ToolFixture::class, 'greet'], 'greeting', description: 'Greets a person');

        $dataCollector = new DataCollector($builder, $registry);

        // The registry is empty before collection — as on a request that never served MCP.
        $this->assertFalse($registry->hasTools());

        $dataCollector->lateCollect();

        $tools = $dataCollector->getTools();
        $this->assertCount(1, $tools);
        $this->assertSame('greeting', $tools[0]['name']);
        $this->assertSame('Greets a person', $tools[0]['description']);
        $this->assertSame(ToolFixture::class.'::greet()', $tools[0]['handler']);
        $this->assertArrayHasKey('inputSchema', $tools[0]);
        $this->assertSame(1, $dataCollector->getTotalCount());
    }

    public function testGetTotalCountReturnsZeroForEmptyRegistry()
    {
        $registry = new Registry(null, new NullLogger());
        $dataCollector = new DataCollector(Server::builder()->setRegistry($registry), $registry);

        $dataCollector->lateCollect();

        $this->assertSame(0, $dataCollector->getTotalCount());
    }
}

class ToolFixture
{
    public function greet(string $name): string
    {
        return 'Hello '.$name;
    }
}
