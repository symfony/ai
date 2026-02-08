<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Postgres\Distance;
use Symfony\AI\Store\Bridge\Postgres\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testAddSingleDocument()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'INSERT INTO embeddings_table (id, metadata, embedding)
                VALUES (:id, :metadata, :vector)
                ON CONFLICT (id) DO UPDATE SET metadata = EXCLUDED.metadata, embedding = EXCLUDED.embedding';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $uuid = Uuid::v4()->toString();

        $statement->expects($this->exactly(3))
            ->method('bindValue')
            ->willReturnCallback(function (string $param, string $value) use ($uuid): bool {
                static $callCount = 0;
                ++$callCount;

                match ($callCount) {
                    1 => $this->assertSame([':id', $uuid], [$param, $value]),
                    2 => $this->assertSame([':metadata', json_encode(['title' => 'Test Document'])], [$param, $value]),
                    3 => $this->assertSame([':vector', '[0.1,0.2,0.3]'], [$param, $value]),
                    default => $this->fail('Unexpected bindValue call'),
                };

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document']));
        $store->add($document);
    }

    public function testAddMultipleDocuments()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $uuid1 = Uuid::v4()->toString();
        $uuid2 = Uuid::v4()->toString();

        $statement->expects($this->exactly(6))
            ->method('bindValue')
            ->willReturnCallback(function (string $param, string $value) use ($uuid1, $uuid2): bool {
                static $callCount = 0;
                ++$callCount;

                match ($callCount) {
                    1 => $this->assertSame([':id', $uuid1], [$param, $value]),
                    2 => $this->assertSame([':metadata', '[]'], [$param, $value]),
                    3 => $this->assertSame([':vector', '[0.1,0.2,0.3]'], [$param, $value]),
                    4 => $this->assertSame([':id', $uuid2], [$param, $value]),
                    5 => $this->assertSame([':metadata', json_encode(['title' => 'Second'])], [$param, $value]),
                    6 => $this->assertSame([':vector', '[0.4,0.5,0.6]'], [$param, $value]),
                    default => $this->fail('Unexpected bindValue call'),
                };

                return true;
            });

        $statement->expects($this->exactly(2))
            ->method('execute');

        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]));
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second']));

        $store->add([$document1, $document2]);
    }

    public function testQueryWithoutMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <-> :embedding) AS score
             FROM embeddings_table

             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':embedding', '[0.1,0.2,0.3]');

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['title' => 'Test Document']),
                    'score' => 0.95,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertEquals($uuid, $results[0]->getId());
        $this->assertSame(0.95, $results[0]->getScore());
        $this->assertSame(['title' => 'Test Document'], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryChangedDistanceMethodWithoutMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding', Distance::Cosine);

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <=> :embedding) AS score
             FROM embeddings_table

             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':embedding', '[0.1,0.2,0.3]');

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['title' => 'Test Document']),
                    'score' => 0.95,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertEquals($uuid, $results[0]->getId());
        $this->assertSame(0.95, $results[0]->getScore());
        $this->assertSame(['title' => 'Test Document'], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryWithMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <-> :embedding) AS score
             FROM embeddings_table
             WHERE (embedding <-> :embedding) <= :maxScore
             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->exactly(2))
            ->method('bindValue')
            ->willReturnCallback(function (string $param, mixed $value): bool {
                static $callCount = 0;
                ++$callCount;

                match ($callCount) {
                    1 => $this->assertSame([':embedding', '[0.1,0.2,0.3]'], [$param, $value]),
                    2 => $this->assertSame([':maxScore', 0.8], [$param, $value]),
                    default => $this->fail('Unexpected bindValue call'),
                };

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxScore' => 0.8]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithMaxScoreAndDifferentDistance()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding', Distance::Cosine);

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <=> :embedding) AS score
             FROM embeddings_table
             WHERE (embedding <=> :embedding) <= :maxScore
             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->exactly(2))
            ->method('bindValue')
            ->willReturnCallback(function (string $param, mixed $value): bool {
                static $callCount = 0;
                ++$callCount;

                match ($callCount) {
                    1 => $this->assertSame([':embedding', '[0.1,0.2,0.3]'], [$param, $value]),
                    2 => $this->assertSame([':maxScore', 0.8], [$param, $value]),
                    default => $this->fail('Unexpected bindValue call'),
                };

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxScore' => 0.8]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomLimit()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <-> :embedding) AS score
             FROM embeddings_table

             ORDER BY score ASC
             LIMIT 10';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':embedding', '[0.7,0.8,0.9]');

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.7, 0.8, 0.9])), ['limit' => 10]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomVectorFieldName()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'custom_vector');

        $expectedQuery = 'SELECT id, custom_vector AS embedding, metadata, (custom_vector <-> :embedding) AS score
             FROM embeddings_table

             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results);
    }

    public function testInitialize()
    {
        $pdo = $this->createMock(\PDO::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $pdo->expects($this->exactly(3))
            ->method('exec')
            ->willReturnCallback(function (string $sql): int {
                /** @var int $callCount */
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame('CREATE EXTENSION IF NOT EXISTS vector', $sql);
                } elseif (2 === $callCount) {
                    $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS embeddings_table', $sql);
                    $this->assertStringContainsString('embedding vector(1536) NOT NULL', $sql);
                } else {
                    $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS embeddings_table_embedding_idx', $sql);
                }

                return 0;
            });

        $store->setup();
    }

    public function testDrop()
    {
        $pdo = $this->createMock(\PDO::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $pdo->expects($this->once())
            ->method('exec')
            ->willReturnCallback(function (string $sql): int {
                $this->assertSame('DROP TABLE IF EXISTS embeddings_table', $sql);

                return 0;
            });

        $store->drop();
    }

    public function testInitializeWithCustomVectorSize()
    {
        $pdo = $this->createMock(\PDO::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $pdo->expects($this->exactly(3))
            ->method('exec')
            ->willReturnCallback(function (string $sql): int {
                /** @var int $callCount */
                static $callCount = 0;
                ++$callCount;

                if (2 === $callCount) {
                    $this->assertStringContainsString('embedding vector(768) NOT NULL', $sql);
                }

                return 0;
            });

        $store->setup(['vector_size' => 768]);
    }

    public function testFromPdo()
    {
        $pdo = $this->createMock(\PDO::class);

        $store = Store::fromPdo($pdo, 'test_table', 'vector_field');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testFromDbalWithPdoDriver()
    {
        $pdo = $this->createMock(\PDO::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn($pdo);

        $store = Store::fromDbal($connection, 'test_table', 'vector_field');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testFromDbalWithNonPdoDriverThrowsException()
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn(new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only DBAL connections using PDO driver are supported.');

        Store::fromDbal($connection, 'test_table');
    }

    public function testQueryWithNullMetadata()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => null,
                    'score' => 0.95,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);
        $this->assertSame([], $results[0]->getMetadata()->getArrayCopy());
    }

    public function testQueryWithCustomWhereExpression()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <-> :embedding) AS score
             FROM embeddings_table
             WHERE metadata->>\'category\' = \'products\'
             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':embedding', '[0.1,0.2,0.3]');

        $statement->expects($this->once())
            ->method('execute');

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

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <-> :embedding) AS score
             FROM embeddings_table
             WHERE (embedding <-> :embedding) <= :maxScore AND (metadata->>\'active\' = \'true\')
             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->exactly(2))
            ->method('bindValue')
            ->willReturnCallback(function (string $param, mixed $value): bool {
                static $callCount = 0;
                ++$callCount;

                match ($callCount) {
                    1 => $this->assertSame([':embedding', '[0.1,0.2,0.3]'], [$param, $value]),
                    2 => $this->assertSame([':maxScore', 0.5], [$param, $value]),
                    default => $this->fail('Unexpected bindValue call'),
                };

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

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

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'SELECT id, embedding AS embedding, metadata, (embedding <-> :embedding) AS score
             FROM embeddings_table
             WHERE metadata->>\'crawlId\' = :crawlId AND id != :currentId
             ORDER BY score ASC
             LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $uuid = Uuid::v4();
        $crawlId = '396af6fe-0dfd-47ed-b222-3dbcced3f38e';

        $statement->expects($this->exactly(3))
            ->method('bindValue')
            ->willReturnCallback(function (string $param, mixed $value) use ($crawlId, $uuid): bool {
                static $callCount = 0;
                ++$callCount;

                match ($callCount) {
                    1 => $this->assertSame([':embedding', '[0.1,0.2,0.3]'], [$param, $value]),
                    2 => $this->assertSame([':crawlId', $crawlId], [$param, $value]),
                    3 => $this->assertSame([':currentId', $uuid->toRfc4122()], [$param, $value]),
                    default => $this->fail('Unexpected bindValue call'),
                };

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

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
                'currentId' => $uuid->toRfc4122(),
            ],
        ]));

        $this->assertCount(1, $results);
        $this->assertSame(0.85, $results[0]->getScore());
        $this->assertSame($crawlId, $results[0]->getMetadata()['crawlId']);
        $this->assertSame('https://example.com', $results[0]->getMetadata()['url']);
    }

    public function testRemoveSingleDocument()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'DELETE FROM embeddings_table WHERE id IN (?)';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($expectedQuery)
            ->willReturn($statement);

        $vectorId = 'test-id';

        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, $vectorId);

        $statement->expects($this->once())
            ->method('execute');

        $store->remove($vectorId);
    }

    public function testRemoveMultipleDocuments()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = 'DELETE FROM embeddings_table WHERE id IN (?,?,?)';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($expectedQuery)
            ->willReturn($statement);

        $ids = ['id-1', 'id-2', 'id-3'];

        $statement->expects($this->exactly(3))
            ->method('bindValue')
            ->willReturnCallback(static function (int $position, string $value) use ($ids): bool {
                static $callCount = 0;
                ++$callCount;

                match ($callCount) {
                    1 => self::assertSame([1, $ids[0]], [$position, $value]),
                    2 => self::assertSame([2, $ids[1]], [$position, $value]),
                    3 => self::assertSame([3, $ids[2]], [$position, $value]),
                    default => self::fail('Unexpected bindValue call'),
                };

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

        $store->remove($ids);
    }

    public function testRemoveWithEmptyArray()
    {
        $pdo = $this->createMock(\PDO::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $pdo->expects($this->never())
            ->method('prepare');

        $store->remove([]);
    }

    public function testStoreSupportsMultipleQueryTypes()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $this->assertTrue($store->supports(VectorQuery::class));
        $this->assertTrue($store->supports(TextQuery::class));
        $this->assertTrue($store->supports(HybridQuery::class));
    }

    public function testQueryWithTextQuery()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = "SELECT id, embedding AS embedding, metadata,
                   ts_rank(to_tsvector('english', metadata->>'_text'), plainto_tsquery('english', :search_text_0)) AS score
            FROM embeddings_table
            WHERE to_tsvector('english', metadata->>'_text') @@ (plainto_tsquery('english', :search_text_0))
            ORDER BY score DESC
            LIMIT 5";

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':search_text_0', 'test query');

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new TextQuery('test query')));

        $this->assertCount(0, $results);
    }

    public function testQueryWithHybridQuery()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new Store($pdo, 'embeddings_table', 'embedding');

        $expectedQuery = "SELECT id, embedding AS embedding, metadata,
                   ((:semantic_ratio * (1 - (embedding <-> :embedding))) +
                    (:keyword_ratio * ts_rank(to_tsvector('english', metadata->>'_text'), (plainto_tsquery('english', :search_text_0))))) AS score
            FROM embeddings_table
            WHERE to_tsvector('english', metadata->>'_text') @@ (plainto_tsquery('english', :search_text_0))
            ORDER BY score DESC
            LIMIT 5";

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedQuery) {
                $this->assertSame($this->normalizeQuery($expectedQuery), $this->normalizeQuery($sql));

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->exactly(4))
            ->method('bindValue');

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'test query', 0.7)));

        $this->assertCount(0, $results);
    }

    public function testStoreSupportsVectorQuery()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new Store($pdo, 'test_table');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreSupportsTextQuery()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new Store($pdo, 'test_table');
        $this->assertTrue($store->supports(TextQuery::class));
    }

    public function testStoreSupportsHybridQuery()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new Store($pdo, 'test_table');
        $this->assertTrue($store->supports(HybridQuery::class));
    }

    private function normalizeQuery(string $query): string
    {
        return trim(preg_replace('/\s+/', ' ', $query));
    }
}
