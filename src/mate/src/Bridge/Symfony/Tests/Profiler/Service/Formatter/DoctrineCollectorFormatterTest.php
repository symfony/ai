<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\DoctrineCollectorFormatter;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DoctrineCollectorFormatterTest extends TestCase
{
    private DoctrineCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new DoctrineCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('db', $this->formatter->getName());
    }

    public function testFormat()
    {
        $collector = $this->createCollector(
            ['default' => 'doctrine.default'],
            [
                'default' => [
                    ['sql' => 'SELECT * FROM users', 'params' => [], 'executionMS' => 0.0012, 'types' => []],
                    ['sql' => 'SELECT * FROM posts WHERE user_id = ?', 'params' => [1], 'executionMS' => 0.0008, 'types' => []],
                ],
            ],
        );

        $result = $this->formatter->format($collector);

        $this->assertSame(2, $result['query_count']);
        $this->assertSame(2.0, $result['total_time_ms']);
        $this->assertSame(['default'], $result['connections']);
        $this->assertCount(2, $result['queries']);
        $this->assertFalse($result['queries_truncated']);
        $this->assertArrayNotHasKey('duplicate_queries', $result);

        $this->assertSame('SELECT * FROM users', $result['queries'][0]['sql']);
        $this->assertSame(1, $result['queries'][0]['count']);
        $this->assertSame(1.2, $result['queries'][0]['total_time_ms']);
        $this->assertSame(1.2, $result['queries'][0]['avg_time_ms']);
        $this->assertSame([], $result['queries'][0]['sample_params']);

        $this->assertSame('SELECT * FROM posts WHERE user_id = ?', $result['queries'][1]['sql']);
        $this->assertSame(1, $result['queries'][1]['count']);
        $this->assertSame(0.8, $result['queries'][1]['total_time_ms']);
        $this->assertSame(0.8, $result['queries'][1]['avg_time_ms']);
        $this->assertSame([1], $result['queries'][1]['sample_params']);
    }

    public function testFormatMultipleConnections()
    {
        $collector = $this->createCollector(
            ['default' => 'doctrine.default', 'legacy' => 'doctrine.legacy'],
            [
                'default' => [
                    ['sql' => 'SELECT 1', 'params' => [], 'executionMS' => 0.001, 'types' => []],
                ],
                'legacy' => [
                    ['sql' => 'SELECT 2', 'params' => [], 'executionMS' => 0.002, 'types' => []],
                ],
            ],
        );

        $result = $this->formatter->format($collector);

        $this->assertSame(2, $result['query_count']);
        $this->assertSame(['default', 'legacy'], $result['connections']);
        $this->assertCount(2, $result['queries']);
    }

    public function testFormatTruncatesQueries()
    {
        $queries = [];
        for ($i = 0; $i < 60; ++$i) {
            $queries[] = ['sql' => 'SELECT '.$i, 'params' => [], 'executionMS' => 0.001, 'types' => []];
        }

        $collector = $this->createCollector(
            ['default' => 'doctrine.default'],
            ['default' => $queries],
        );

        $result = $this->formatter->format($collector);

        $this->assertSame(60, $result['query_count']);
        $this->assertCount(50, $result['queries']);
        $this->assertTrue($result['queries_truncated']);
    }

    public function testFormatDetectsDuplicateQueries()
    {
        $collector = $this->createCollector(
            ['default' => 'doctrine.default'],
            [
                'default' => [
                    ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1], 'executionMS' => 0.001, 'types' => []],
                    ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [2], 'executionMS' => 0.002, 'types' => []],
                    ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [3], 'executionMS' => 0.003, 'types' => []],
                    ['sql' => 'SELECT * FROM posts', 'params' => [], 'executionMS' => 0.001, 'types' => []],
                ],
            ],
        );

        $result = $this->formatter->format($collector);

        $this->assertCount(2, $result['queries']);
        // sorted by total_time_ms descending
        $this->assertSame('SELECT * FROM users WHERE id = ?', $result['queries'][0]['sql']);
        $this->assertSame(3, $result['queries'][0]['count']);
        $this->assertSame(6.0, $result['queries'][0]['total_time_ms']);
        $this->assertSame(2.0, $result['queries'][0]['avg_time_ms']);
        $this->assertSame([1], $result['queries'][0]['sample_params']);
        $this->assertSame('SELECT * FROM posts', $result['queries'][1]['sql']);
        $this->assertSame(1, $result['queries'][1]['count']);
        $this->assertSame(1.0, $result['queries'][1]['total_time_ms']);
        $this->assertSame(1.0, $result['queries'][1]['avg_time_ms']);
        $this->assertSame([], $result['queries'][1]['sample_params']);
        $this->assertArrayNotHasKey('duplicate_queries', $result);
    }

    public function testFormatTruncatesLongSampleParams()
    {
        $longParam = str_repeat('a', 150);

        $collector = $this->createCollector(
            ['default' => 'doctrine.default'],
            [
                'default' => [
                    ['sql' => 'SELECT * FROM users WHERE bio = ?', 'params' => [$longParam], 'executionMS' => 0.001, 'types' => []],
                ],
            ],
        );

        $result = $this->formatter->format($collector);

        $this->assertSame(103, \strlen($result['queries'][0]['sample_params'][0]));
        $this->assertStringEndsWith('...', $result['queries'][0]['sample_params'][0]);
    }

    public function testFormatNoQueries()
    {
        $collector = $this->createCollector(
            ['default' => 'doctrine.default'],
            ['default' => []],
        );

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['query_count']);
        $this->assertSame(0.0, $result['total_time_ms']);
        $this->assertSame([], $result['queries']);
        $this->assertFalse($result['queries_truncated']);
    }

    public function testGetSummary()
    {
        $collector = $this->createCollector(
            ['default' => 'doctrine.default'],
            [
                'default' => [
                    ['sql' => 'SELECT 1', 'params' => [], 'executionMS' => 0.005, 'types' => []],
                    ['sql' => 'SELECT 2', 'params' => [], 'executionMS' => 0.003, 'types' => []],
                ],
            ],
        );

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(2, $result['query_count']);
        $this->assertSame(8.0, $result['total_time_ms']);
        $this->assertSame(0, $result['duplicate_query_count']);
        $this->assertArrayNotHasKey('queries', $result);
        $this->assertArrayNotHasKey('connections', $result);
    }

    public function testGetSummaryWithDuplicates()
    {
        $collector = $this->createCollector(
            ['default' => 'doctrine.default'],
            [
                'default' => [
                    ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1], 'executionMS' => 0.001, 'types' => []],
                    ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [2], 'executionMS' => 0.001, 'types' => []],
                    ['sql' => 'SELECT * FROM posts WHERE id = ?', 'params' => [1], 'executionMS' => 0.001, 'types' => []],
                    ['sql' => 'SELECT * FROM posts WHERE id = ?', 'params' => [2], 'executionMS' => 0.001, 'types' => []],
                    ['sql' => 'SELECT 1', 'params' => [], 'executionMS' => 0.001, 'types' => []],
                ],
            ],
        );

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(5, $result['query_count']);
        $this->assertSame(2, $result['duplicate_query_count']);
    }

    /**
     * @param array<string, string>                                                                      $connections
     * @param array<string, list<array{sql: string, params: mixed, executionMS: float, types: mixed[]}>> $queries
     */
    private function createCollector(array $connections, array $queries): DoctrineDataCollector
    {
        $collector = $this->createMock(DoctrineDataCollector::class);

        $collector->method('getConnections')->willReturn($connections);
        $collector->method('getQueries')->willReturn($queries);

        $totalCount = 0;
        $totalTime = 0.0;
        foreach ($queries as $connectionQueries) {
            $totalCount += \count($connectionQueries);
            foreach ($connectionQueries as $query) {
                $totalTime += $query['executionMS'];
            }
        }

        $collector->method('getQueryCount')->willReturn($totalCount);
        $collector->method('getTime')->willReturn($totalTime);

        return $collector;
    }
}
