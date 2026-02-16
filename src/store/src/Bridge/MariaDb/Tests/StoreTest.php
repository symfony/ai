<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\MariaDb\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\MariaDb\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testQueryWithMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        // Expected SQL query with max score
        $expectedQuery = <<<'SQL'
            SELECT id, VEC_ToText(`embedding`) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) AS score
            FROM embeddings_table
            WHERE VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) <= :maxScore
            ORDER BY score ASC
            LIMIT 5
            SQL;

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($expectedQuery)
            ->willReturn($statement);

        $uuid = Uuid::v4();
        $vectorData = [0.1, 0.2, 0.3];
        $maxScore = 0.8;

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->isNull());

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => json_encode($vectorData),
                    'metadata' => json_encode(['title' => 'Test Document']),
                    'score' => 0.85,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector($vectorData)), ['maxScore' => $maxScore]));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame(0.85, $results[0]->getScore());
        $this->assertSame(['title' => 'Test Document'], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryWithoutMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        // Expected SQL query without maxScore
        $expectedQuery = <<<'SQL'
            SELECT id, VEC_ToText(`embedding`) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) AS score
            FROM embeddings_table

            ORDER BY score ASC
            LIMIT 5
            SQL;

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($expectedQuery)
            ->willReturn($statement);

        $uuid = Uuid::v4();
        $vectorData = [0.1, 0.2, 0.3];

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->isNull());

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => json_encode($vectorData),
                    'metadata' => json_encode(['title' => 'Test Document']),
                    'score' => 0.95,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector($vectorData))));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame(0.95, $results[0]->getScore());
    }

    public function testQueryWithCustomLimit()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        // Expected SQL query with custom limit
        $expectedQuery = <<<'SQL'
            SELECT id, VEC_ToText(`embedding`) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) AS score
            FROM embeddings_table

            ORDER BY score ASC
            LIMIT 10
            SQL;

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($expectedQuery)
            ->willReturn($statement);

        $vectorData = [0.1, 0.2, 0.3];

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->isNull());

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector($vectorData)), ['limit' => 10]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomWhereExpression()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_idx', 'embedding');

        $expectedQuery = <<<SQL
            SELECT id, VEC_ToText(`embedding`) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) AS score
            FROM embeddings_table
            WHERE metadata->>'category' = 'products'
            ORDER BY score
            ASC LIMIT 5
            SQL;

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->isNull());

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['where' => 'metadata->>\'category\' = \'products\'']));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomWhereExpressionAndMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_idx', 'embedding');

        $expectedQuery = <<<SQL
            SELECT id, VEC_ToText(`embedding`) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) AS score
            FROM embeddings_table
            WHERE VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) <= :maxScore
                AND (metadata->>'active' = 'true')
            ORDER BY score ASC
            LIMIT 5
            SQL;

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->isNull());

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), [
            'maxScore' => 0.5,
            'where' => 'metadata->>\'active\' = \'true\'',
        ]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomWhereExpressionAndParams()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_idx', 'embedding');

        $expectedQuery = <<<SQL
            SELECT id, VEC_ToText(`embedding`) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(`embedding`, VEC_FromText(:embedding)) AS score
            FROM embeddings_table
            WHERE metadata->>'crawlId' = :crawlId
                AND id != :currentId
            ORDER BY score
            ASC LIMIT 5
            SQL;

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $crawlId = '396af6fe-0dfd-47ed-b222-3dbcced3f38e';

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->isNull());

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => Uuid::v4()->toRfc4122(),
                    'embedding' => '[0.4,0.5,0.6]',
                    'metadata' => json_encode(['crawlId' => $crawlId, 'url' => 'https://example.com']),
                    'score' => 0.85,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), [
            'where' => 'metadata->>\'crawlId\' = :crawlId AND id != :currentId',
            'params' => [
                'crawlId' => $crawlId,
                'currentId' => Uuid::v4()->toRfc4122(),
            ],
        ]));

        $this->assertCount(1, $results);
        $this->assertSame(0.85, $results[0]->getScore());
        $this->assertSame($crawlId, $results[0]->getMetadata()['crawlId']);
        $this->assertSame('https://example.com', $results[0]->getMetadata()['url']);
    }

    public function testItCanDrop()
    {
        $pdo = $this->createMock(\PDO::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        $pdo->expects($this->once())
            ->method('exec')
            ->with('DROP TABLE IF EXISTS embeddings_table')
            ->willReturn(1);

        $store->drop();
    }

    public function testRemoveWithSingleId()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        $id = '123e4567-e89b-12d3-a456-426614174000';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM embeddings_table WHERE id IN (?)')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, $id);

        $statement->expects($this->once())
            ->method('execute');

        $store->remove($id);
    }

    public function testRemoveWithMultipleIds()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        $ids = [
            '123e4567-e89b-12d3-a456-426614174000',
            '223e4567-e89b-12d3-a456-426614174001',
            '323e4567-e89b-12d3-a456-426614174002',
        ];

        $pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM embeddings_table WHERE id IN (?, ?, ?)')
            ->willReturn($statement);

        $statement->expects($this->exactly(3))
            ->method('bindValue')
            ->willReturnCallback(static function ($index, $value) use ($ids) {
                self::assertSame($ids[$index - 1], $value);

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

        $store->remove($ids);
    }

    public function testRemoveWithEmptyArray()
    {
        $pdo = $this->createMock(\PDO::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        $pdo->expects($this->never())
            ->method('prepare');

        $store->remove([]);
    }

    public function testStoreSupportsVectorQuery()
    {
        $connection = $this->createMock(\PDO::class);
        $store = new Store($connection, 'test_vectors', 'vector_index', 'embedding');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $connection = $this->createMock(\PDO::class);
        $store = new Store($connection, 'test_vectors', 'vector_index', 'embedding');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $connection = $this->createMock(\PDO::class);
        $store = new Store($connection, 'test_vectors', 'vector_index', 'embedding');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    private function normalizeQuery(string $query): string
    {
        return trim(preg_replace('/\s+/', ' ', $query));
    }
}
