<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\MariaDb;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\MariaDb\Store;
use Symfony\AI\Store\Document\VectorDocument;
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
            SELECT id, VEC_ToText(embedding) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(embedding, VEC_FromText(:embedding)) AS score
            FROM embeddings_table
            WHERE VEC_DISTANCE_EUCLIDEAN(embedding, VEC_FromText(:embedding)) <= :maxScore
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
            ->with([
                'embedding' => json_encode($vectorData),
                'maxScore' => $maxScore,
            ]);

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toBinary(),
                    'embedding' => json_encode($vectorData),
                    'metadata' => json_encode(['title' => 'Test Document']),
                    'score' => 0.85,
                ],
            ]);

        $results = $store->query(new Vector($vectorData), ['maxScore' => $maxScore]);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame(0.85, $results[0]->score);
        $this->assertSame(['title' => 'Test Document'], $results[0]->metadata->getArrayCopy());
    }

    public function testQueryWithoutMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        // Expected SQL query without maxScore
        $expectedQuery = <<<'SQL'
            SELECT id, VEC_ToText(embedding) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(embedding, VEC_FromText(:embedding)) AS score
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
            ->with(['embedding' => json_encode($vectorData)]);

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toBinary(),
                    'embedding' => json_encode($vectorData),
                    'metadata' => json_encode(['title' => 'Test Document']),
                    'score' => 0.95,
                ],
            ]);

        $results = $store->query(new Vector($vectorData));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame(0.95, $results[0]->score);
    }

    public function testQueryWithCustomLimit()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding_index', 'embedding');

        // Expected SQL query with custom limit
        $expectedQuery = <<<'SQL'
            SELECT id, VEC_ToText(embedding) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(embedding, VEC_FromText(:embedding)) AS score
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
            ->with(['embedding' => json_encode($vectorData)]);

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = $store->query(new Vector($vectorData), ['limit' => 10]);

        $this->assertCount(0, $results);
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
}
