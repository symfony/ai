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

use Mcp\Server\Builder;
use Mcp\Server\Capability\Registry;
use Mcp\Server\Server;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DataCollectorTest extends TestCase
{
    public function testGetNameReturnsShortName()
    {
        $registry = new Registry(null, new NullLogger());
        $dataCollector = $this->createCollector($registry);

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

        $dataCollector = $this->createCollector($registry, $builder);

        // The registry is empty before collection — as on a request that never served MCP.
        $this->assertFalse($registry->hasTools());

        $dataCollector->lateCollect();

        $tools = $dataCollector->getServers()['default']['tools'];
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
        $dataCollector = $this->createCollector($registry);

        $dataCollector->lateCollect();

        $this->assertSame(0, $dataCollector->getTotalCount());
    }

    public function testCollectsEveryServerSeparately()
    {
        $public = new Registry(null, new NullLogger());
        $editors = new Registry(null, new NullLogger());

        $dataCollector = new DataCollector(
            new ServiceLocator([
                'public' => static fn (): Builder => Server::builder()->setRegistry($public),
                'editors' => static fn (): Builder => Server::builder()->setRegistry($editors)
                    ->addTool([ToolFixture::class, 'greet'], 'greeting'),
            ]),
            new ServiceLocator([
                'public' => static fn (): Registry => $public,
                'editors' => static fn (): Registry => $editors,
            ]),
        );

        $dataCollector->lateCollect();

        $servers = $dataCollector->getServers();
        $this->assertSame(['public', 'editors'], array_keys($servers));
        $this->assertSame([], $servers['public']['tools']);
        $this->assertCount(1, $servers['editors']['tools']);
        $this->assertSame(2, $dataCollector->getServerCount());
        $this->assertSame(1, $dataCollector->getTotalCount());
    }

    public function testTheCollectorsOwnBuildDoesNotLog()
    {
        $logger = new RecordingLogger();
        $registry = new Registry(null, new NullLogger());
        $builder = Server::builder()
            ->setRegistry($registry)
            ->setLogger($logger)
            ->addTool([ToolFixture::class, 'greet'], 'greeting');

        $this->createCollector($registry, $builder)->lateCollect();

        $this->assertSame([], $logger->records);
    }

    public function testTheCollectorLeavesTheSharedBuildersLoggerInPlace()
    {
        $logger = new RecordingLogger();
        $registry = new Registry(null, new NullLogger());
        $builder = Server::builder()
            ->setRegistry($registry)
            ->setLogger($logger)
            ->addTool([ToolFixture::class, 'greet'], 'greeting');

        $this->createCollector($registry, $builder)->lateCollect();

        // The builder survives the request in a worker, where the next MCP request still needs it.
        $builder->build();

        $this->assertNotSame([], $logger->records);
    }

    private function createCollector(Registry $registry, ?Builder $builder = null): DataCollector
    {
        $builder ??= Server::builder()->setRegistry($registry);

        return new DataCollector(
            new ServiceLocator(['default' => static fn (): Builder => $builder]),
            new ServiceLocator(['default' => static fn (): Registry => $registry]),
        );
    }
}

class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    public array $records = [];

    /**
     * Untyped $message so the signature fits psr/log 1 as well as 3.
     *
     * @param \Stringable|string   $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = $level.': '.$message;
    }
}

class ToolFixture
{
    public function greet(string $name): string
    {
        return 'Hello '.$name;
    }
}
