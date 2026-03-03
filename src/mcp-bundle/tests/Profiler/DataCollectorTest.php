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
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DataCollectorTest extends TestCase
{
    public function testGetNameReturnsShortName()
    {
        $registry = new Registry(null, new NullLogger());
        $traceableRegistry = new TraceableRegistry($registry);
        $dataCollector = new DataCollector($traceableRegistry);

        $name = $dataCollector->getName();

        $this->assertSame('mcp', $name);
        // Verify it's a short name, not a class name
        $this->assertStringNotContainsString('\\', $name);
        $this->assertStringNotContainsString('DataCollector', $name);
    }

    public function testLateCollectPopulatesData()
    {
        $registry = new Registry(null, new NullLogger());
        $traceableRegistry = new TraceableRegistry($registry);
        $dataCollector = new DataCollector($traceableRegistry);

        $dataCollector->lateCollect();

        // Verify data is collected
        $this->assertIsArray($dataCollector->getTools());
        $this->assertIsArray($dataCollector->getPrompts());
        $this->assertIsArray($dataCollector->getResources());
        $this->assertIsArray($dataCollector->getResourceTemplates());
    }

    public function testGetTotalCountReturnsZeroForEmptyRegistry()
    {
        $registry = new Registry(null, new NullLogger());
        $traceableRegistry = new TraceableRegistry($registry);
        $dataCollector = new DataCollector($traceableRegistry);

        $dataCollector->lateCollect();

        $this->assertSame(0, $dataCollector->getTotalCount());
    }
}
