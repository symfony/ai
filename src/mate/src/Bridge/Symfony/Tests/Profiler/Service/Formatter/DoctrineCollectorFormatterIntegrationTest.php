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
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\DoctrineCollectorFormatter;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration test using real DoctrineDataCollector with DebugDataHolder.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DoctrineCollectorFormatterIntegrationTest extends TestCase
{
    private DoctrineCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new DoctrineCollectorFormatter();
    }

    public function testFormatWithRealCollector()
    {
        $collector = $this->createRealCollector([
            'default' => [
                ['sql' => 'SELECT * FROM users', 'params' => []],
                ['sql' => 'SELECT * FROM posts WHERE user_id = ?', 'params' => [1 => 42]],
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(2, $result['query_count']);
        $this->assertArrayHasKey('total_time_ms', $result);
        $this->assertGreaterThanOrEqual(0.0, $result['total_time_ms']);
        $this->assertSame(['default'], $result['connections']);
        $this->assertCount(2, $result['queries']);
        $this->assertFalse($result['queries_truncated']);

        $this->assertSame('default', $result['queries'][0]['connection']);
        $this->assertSame('SELECT * FROM users', $result['queries'][0]['sql']);
        $this->assertArrayHasKey('time_ms', $result['queries'][0]);

        $this->assertSame('SELECT * FROM posts WHERE user_id = ?', $result['queries'][1]['sql']);
    }

    public function testFormatDetectsDuplicatesWithRealCollector()
    {
        $collector = $this->createRealCollector([
            'default' => [
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1 => 1]],
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1 => 2]],
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1 => 3]],
                ['sql' => 'SELECT * FROM posts', 'params' => []],
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(4, $result['query_count']);
        $this->assertCount(4, $result['queries']);
        $this->assertCount(1, $result['duplicate_queries']);
        $this->assertSame('SELECT * FROM users WHERE id = ?', $result['duplicate_queries'][0]['sql']);
        $this->assertSame(3, $result['duplicate_queries'][0]['count']);
    }

    public function testGetSummaryWithRealCollector()
    {
        $collector = $this->createRealCollector([
            'default' => [
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1 => 1]],
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1 => 2]],
                ['sql' => 'SELECT * FROM posts', 'params' => []],
            ],
        ]);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(3, $result['query_count']);
        $this->assertArrayHasKey('total_time_ms', $result);
        $this->assertSame(1, $result['duplicate_query_count']);
        $this->assertArrayNotHasKey('queries', $result);
    }

    public function testFormatMultipleConnectionsWithRealCollector()
    {
        $collector = $this->createRealCollector([
            'default' => [
                ['sql' => 'SELECT 1', 'params' => []],
            ],
            'legacy' => [
                ['sql' => 'SELECT 2', 'params' => []],
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(2, $result['query_count']);
        $this->assertSame(['default', 'legacy'], $result['connections']);
        $this->assertCount(2, $result['queries']);
        $this->assertSame('default', $result['queries'][0]['connection']);
        $this->assertSame('legacy', $result['queries'][1]['connection']);
    }

    /**
     * @param array<string, list<array{sql: string, params: array<int, mixed>}>> $queriesByConnection
     */
    private function createRealCollector(array $queriesByConnection): DoctrineDataCollector
    {
        $debugDataHolder = new DebugDataHolder();

        $connectionNames = [];
        foreach ($queriesByConnection as $connection => $queries) {
            $connectionNames[$connection] = 'doctrine.'.$connection.'_connection';

            foreach ($queries as $queryData) {
                $query = new Query($queryData['sql']);
                $query->start();

                foreach ($queryData['params'] as $index => $value) {
                    $query->setValue($index, $value, \Doctrine\DBAL\ParameterType::STRING);
                }

                $query->stop();
                $debugDataHolder->addQuery($connection, $query);
            }
        }

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnectionNames')->willReturn($connectionNames);
        $registry->method('getManagerNames')->willReturn([]);
        $registry->method('getManagers')->willReturn([]);

        $collector = new DoctrineDataCollector($registry, false, $debugDataHolder);
        $collector->collect(new Request(), new Response());

        return $collector;
    }
}
