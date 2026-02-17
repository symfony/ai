<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\Postgres;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Postgres\HybridStore;
use Symfony\AI\Store\Bridge\Postgres\ReciprocalRankFusion;
use Symfony\AI\Store\Bridge\Postgres\TextSearch\Bm25TextSearchStrategy;
use Symfony\AI\Store\Bridge\Postgres\TextSearch\PostgresTextSearchStrategy;
use Symfony\AI\Store\Bridge\Postgres\TextSearch\TextSearchStrategyInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

final class HybridStoreTest extends TestCase
{
    public function testConstructorValidatesSemanticRatio()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The semantic ratio must be between 0.0 and 1.0');

        $pdo = $this->createMock(\PDO::class);
        new HybridStore($pdo, 'test_table', semanticRatio: 1.5);
    }

    public function testConstructorValidatesNegativeSemanticRatio()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The semantic ratio must be between 0.0 and 1.0');

        $pdo = $this->createMock(\PDO::class);
        new HybridStore($pdo, 'test_table', semanticRatio: -0.5);
    }

    public function testConstructorValidatesFuzzyWeight()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The fuzzy weight must be between 0.0 and 1.0');

        $pdo = $this->createMock(\PDO::class);
        new HybridStore($pdo, 'test_table', fuzzyWeight: 1.5);
    }

    public function testConstructorUsesDefaultTextSearchStrategy()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new HybridStore($pdo, 'test_table');

        $this->assertInstanceOf(PostgresTextSearchStrategy::class, $store->getTextSearchStrategy());
    }

    public function testConstructorUsesCustomTextSearchStrategy()
    {
        $pdo = $this->createMock(\PDO::class);
        $customStrategy = new Bm25TextSearchStrategy();
        $store = new HybridStore($pdo, 'test_table', textSearchStrategy: $customStrategy);

        $this->assertSame($customStrategy, $store->getTextSearchStrategy());
    }

    public function testConstructorUsesDefaultRrf()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new HybridStore($pdo, 'test_table');

        $this->assertInstanceOf(ReciprocalRankFusion::class, $store->getRrf());
        $this->assertSame(60, $store->getRrf()->getK());
    }

    public function testConstructorUsesCustomRrf()
    {
        $pdo = $this->createMock(\PDO::class);
        $customRrf = new ReciprocalRankFusion(k: 100, normalizeScores: false);
        $store = new HybridStore($pdo, 'test_table', rrf: $customRrf);

        $this->assertSame($customRrf, $store->getRrf());
        $this->assertSame(100, $store->getRrf()->getK());
    }

    public function testSetupCreatesTableWithFullTextSearchSupport()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new HybridStore($pdo, 'hybrid_table');

        $pdo->expects($this->exactly(10))
            ->method('exec')
            ->willReturnCallback(function (string $sql): int {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame('CREATE EXTENSION IF NOT EXISTS vector', $sql);
                } elseif (2 === $callCount) {
                    $this->assertSame('CREATE EXTENSION IF NOT EXISTS pg_trgm', $sql);
                } elseif (3 === $callCount) {
                    $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS hybrid_table', $sql);
                    $this->assertStringContainsString('content TEXT NOT NULL', $sql);
                    $this->assertStringContainsString('embedding vector(1536) NOT NULL', $sql);
                } elseif (4 === $callCount) {
                    $this->assertStringContainsString('ALTER TABLE hybrid_table ADD COLUMN IF NOT EXISTS search_text TEXT', $sql);
                } elseif (5 === $callCount) {
                    $this->assertStringContainsString('CREATE OR REPLACE FUNCTION update_search_text()', $sql);
                } elseif (6 === $callCount) {
                    $this->assertStringContainsString('CREATE TRIGGER trigger_update_search_text', $sql);
                } elseif (7 === $callCount) {
                    $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS hybrid_table_embedding_idx', $sql);
                } elseif (8 === $callCount) {
                    // TextSearchStrategy adds content_tsv column via ALTER TABLE
                    $this->assertStringContainsString('ALTER TABLE hybrid_table ADD COLUMN IF NOT EXISTS content_tsv', $sql);
                    $this->assertStringContainsString('GENERATED ALWAYS AS (to_tsvector(\'simple\', content)) STORED', $sql);
                } elseif (9 === $callCount) {
                    // TextSearchStrategy creates GIN index for content_tsv
                    $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS hybrid_table_content_tsv_idx', $sql);
                    $this->assertStringContainsString('USING gin(content_tsv)', $sql);
                } else {
                    $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS hybrid_table_search_text_trgm_idx', $sql);
                    $this->assertStringContainsString('USING gin(search_text gin_trgm_ops)', $sql);
                }

                return 0;
            });

        $store->setup();
    }

    public function testSetupExecutesTextSearchStrategySetupSql()
    {
        $pdo = $this->createMock(\PDO::class);

        $mockStrategy = $this->createMock(TextSearchStrategyInterface::class);
        $mockStrategy->expects($this->once())
            ->method('getSetupSql')
            ->with('hybrid_table', 'content', 'simple')
            ->willReturn([
                'CREATE INDEX custom_idx ON hybrid_table USING gin(content)',
            ]);

        $store = new HybridStore($pdo, 'hybrid_table', textSearchStrategy: $mockStrategy);

        $execCalls = [];
        $pdo->expects($this->any())
            ->method('exec')
            ->willReturnCallback(function (string $sql) use (&$execCalls): int {
                $execCalls[] = $sql;

                return 0;
            });

        $store->setup();

        $this->assertContains('CREATE INDEX custom_idx ON hybrid_table USING gin(content)', $execCalls);
        $this->assertNotEmpty($execCalls, 'Expected at least one exec() call');
    }

    public function testAddDocument()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore($pdo, 'hybrid_table');

        $expectedSql = 'INSERT INTO hybrid_table (id, metadata, content, embedding)
                VALUES (:id, :metadata, :content, :vector)
                ON CONFLICT (id) DO UPDATE SET
                    metadata = EXCLUDED.metadata,
                    content = EXCLUDED.content,
                    embedding = EXCLUDED.embedding';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedSql) {
                return $this->normalizeQuery($sql) === $this->normalizeQuery($expectedSql);
            }))
            ->willReturn($statement);

        $uuid = Uuid::v4();

        $statement->expects($this->exactly(4))
            ->method('bindValue')
            ->willReturnCallback(function ($param, $value) use ($uuid) {
                static $calls = [];
                $calls[] = [$param, $value];

                if (4 === \count($calls)) {
                    self::assertSame(':id', $calls[0][0]);
                    self::assertSame($uuid->toRfc4122(), $calls[0][1]);
                    self::assertSame(':metadata', $calls[1][0]);
                    self::assertSame(json_encode(['_text' => 'Test content', 'category' => 'test']), $calls[1][1]);
                    self::assertSame(':content', $calls[2][0]);
                    self::assertSame('Test content', $calls[2][1]);
                    self::assertSame(':vector', $calls[3][0]);
                    self::assertSame('[0.1,0.2,0.3]', $calls[3][1]);
                }

                return true;
            });

        $statement->expects($this->once())
            ->method('execute');

        $metadata = new Metadata(['_text' => 'Test content', 'category' => 'test']);
        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), $metadata);
        $store->add($document);
    }

    public function testAddMultipleDocuments()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore($pdo, 'hybrid_table');

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $statement->expects($this->exactly(8))
            ->method('bindValue')
            ->willReturnCallback(function ($param, $value) use ($uuid1, $uuid2) {
                static $calls = [];
                $calls[] = [$param, $value];

                // After 4 calls, check first document
                if (4 === \count($calls)) {
                    self::assertSame(':id', $calls[0][0]);
                    self::assertSame($uuid1->toRfc4122(), $calls[0][1]);
                    self::assertSame(':content', $calls[2][0]);
                    self::assertSame('First document', $calls[2][1]);
                }

                // After 8 calls, check second document
                if (8 === \count($calls)) {
                    self::assertSame(':id', $calls[4][0]);
                    self::assertSame($uuid2->toRfc4122(), $calls[4][1]);
                    self::assertSame(':content', $calls[6][0]);
                    self::assertSame('Second document', $calls[6][1]);
                }

                return true;
            });

        $statement->expects($this->exactly(2))
            ->method('execute');

        $metadata1 = new Metadata(['_text' => 'First document']);
        $metadata2 = new Metadata(['_text' => 'Second document']);

        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]), $metadata1);
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), $metadata2);

        $store->add([$document1, $document2]);
    }

    public function testPureVectorSearch()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        // Disable score normalization for this test
        $rrf = new ReciprocalRankFusion(normalizeScores: false);
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 1.0, rrf: $rrf);

        $expectedSql = 'SELECT id, embedding AS embedding, metadata, (embedding <-> :embedding) AS score
            FROM hybrid_table

            ORDER BY score ASC
            LIMIT 5';

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) use ($expectedSql) {
                return $this->normalizeQuery($sql) === $this->normalizeQuery($expectedSql);
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with(['embedding' => '[0.1,0.2,0.3]']);

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['text' => 'Test Document']),
                    'score' => 0.05,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame(0.05, $results[0]->getScore());
    }

    public function testPureKeywordSearchWithPostgresStrategy()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $rrf = new ReciprocalRankFusion(normalizeScores: false);
        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 0.0,
            textSearchStrategy: new PostgresTextSearchStrategy(),
            rrf: $rrf
        );

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Verify PostgreSQL native FTS structure
                $this->assertStringContainsString('WITH', $sql);
                $this->assertStringContainsString('fts_search AS', $sql);
                $this->assertStringContainsString('ts_rank_cd', $sql);
                $this->assertStringContainsString('plainto_tsquery', $sql);
                $this->assertStringContainsString('content_tsv @@', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return isset($params['query']) && 'PostgreSQL' === $params['query'];
            }));

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => null,
                    'metadata' => json_encode(['text' => 'PostgreSQL is awesome']),
                    'score' => 0.5,
                ],
            ]);

        $results = iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'PostgreSQL', 0.0)));

        $this->assertCount(1, $results);
        $this->assertSame(0.5, $results[0]->getScore());
        // FTS-only results should have NullVector
        $this->assertInstanceOf(NullVector::class, $results[0]->getVector());
    }

    public function testPureKeywordSearchWithBm25Strategy()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $rrf = new ReciprocalRankFusion(normalizeScores: false);
        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 0.0,
            textSearchStrategy: new Bm25TextSearchStrategy(bm25Language: 'en'),
            rrf: $rrf
        );

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Verify BM25 structure
                $this->assertStringContainsString('WITH', $sql);
                $this->assertStringContainsString('bm25_search AS', $sql);
                $this->assertStringContainsString('bm25topk(', $sql);
                $this->assertStringContainsString('bm25_with_metadata AS', $sql);
                $this->assertStringContainsString('DISTINCT ON', $sql);

                // Should NOT contain native FTS functions
                $this->assertStringNotContainsString('ts_rank_cd', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return isset($params['query']) && 'PostgreSQL' === $params['query'];
            }));

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => null,
                    'metadata' => json_encode(['text' => 'PostgreSQL is awesome']),
                    'score' => 0.5,
                ],
            ]);

        $results = iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'PostgreSQL', 0.0)));

        $this->assertCount(1, $results);
        $this->assertSame(0.5, $results[0]->getScore());
    }

    public function testHybridSearchWithRRF()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: false);
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.5, rrf: $rrf);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Check for RRF CTE structure
                $this->assertStringContainsString('WITH vector_scores AS', $sql);
                $this->assertStringContainsString('fuzzy_scores AS', $sql);
                $this->assertStringContainsString('combined_results AS', $sql);
                $this->assertStringContainsString('ROW_NUMBER() OVER', $sql);
                $this->assertStringContainsString('FULL OUTER JOIN', $sql);
                $this->assertStringContainsString('ORDER BY score DESC', $sql);

                // Should contain fuzzy matching
                $this->assertStringContainsString('word_similarity', $sql);

                // Should contain RRF formula with k=60
                $this->assertStringContainsString('60 +', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return isset($params['embedding']) && isset($params['query']);
            }));

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['text' => 'PostgreSQL database']),
                    'score' => 0.025,
                ],
            ]);

        $results = iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'PostgreSQL', 0.5)));

        $this->assertCount(1, $results);
        $this->assertSame(0.025, $results[0]->getScore());
    }

    public function testQueryWithDefaultMaxScore()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 1.0,
            defaultMaxScore: 0.8
        );

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                $this->assertStringContainsString('WHERE (embedding <-> :embedding) <= :maxScore', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return isset($params['embedding'])
                    && isset($params['maxScore'])
                    && 0.8 === $params['maxScore'];
            }));

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results);
    }

    public function testQueryWithMaxScoreOverride()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 1.0,
            defaultMaxScore: 0.8
        );

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                // Should use override value 0.5, not default 0.8
                return isset($params['maxScore']) && 0.5 === $params['maxScore'];
            }));

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxScore' => 0.5]));

        $this->assertCount(0, $results);
    }

    public function testQueryWithMinScoreFilter()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $rrf = new ReciprocalRankFusion(normalizeScores: false);
        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 1.0,
            rrf: $rrf,
            defaultMinScore: 0.5
        );

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid1->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['text' => 'High score']),
                    'score' => 0.8,
                ],
                [
                    'id' => $uuid2->toRfc4122(),
                    'embedding' => '[0.4,0.5,0.6]',
                    'metadata' => json_encode(['text' => 'Low score']),
                    'score' => 0.3,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        // Only high score result should be returned
        $this->assertCount(1, $results);
        $this->assertSame(0.8, $results[0]->getScore());
    }

    public function testQueryWithCustomRRFK()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $rrf = new ReciprocalRankFusion(k: 100);
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.5, rrf: $rrf);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Check for RRF constant 100 in the formula
                $this->assertStringContainsString('100 +', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'test', 0.5)));
    }

    public function testDrop()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new HybridStore($pdo, 'hybrid_table');

        $pdo->expects($this->once())
            ->method('exec')
            ->with('DROP TABLE IF EXISTS hybrid_table');

        $store->drop();
    }

    public function testQueryWithCustomLimit()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 1.0);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                $this->assertStringContainsString('LIMIT 10', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 10]));
    }

    public function testPureKeywordSearchReturnsEmptyWhenNoMatch()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.0);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $results = iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'zzzzzzzzzzzzz', 0.0)));

        $this->assertCount(0, $results);
    }

    public function testFuzzyMatchingWithWordSimilarity()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 0.5,
            fuzzyWeight: 0.3,
            fuzzyPrimaryThreshold: 0.3,
            fuzzySecondaryThreshold: 0.25,
            fuzzyStrictThreshold: 0.2
        );

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Verify fuzzy_scores CTE exists
                $this->assertStringContainsString('fuzzy_scores AS', $sql);

                // Verify word_similarity function is used
                $this->assertStringContainsString('word_similarity(:query, search_text)', $sql);

                // Verify custom thresholds are applied
                $this->assertStringContainsString('0.300000', $sql);
                $this->assertStringContainsString('0.250000', $sql);
                $this->assertStringContainsString('0.200000', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())->method('execute');
        $statement->expects($this->once())->method('fetchAll')->willReturn([]);

        iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'test', 0.5)));
    }

    public function testSearchableAttributesWithBoost()
    {
        $pdo = $this->createMock(\PDO::class);

        $searchableAttributes = [
            'title' => ['boost' => 2.0, 'metadata_key' => 'title'],
            'overview' => ['boost' => 1.0, 'metadata_key' => 'overview'],
        ];

        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            searchableAttributes: $searchableAttributes
        );

        $pdo->expects($this->exactly(10))
            ->method('exec')
            ->willReturnCallback(function (string $sql): int {
                static $callCount = 0;
                ++$callCount;

                if (3 === $callCount) {
                    // Verify separate tsvector columns for each attribute
                    $this->assertStringContainsString('title_tsv tsvector GENERATED ALWAYS AS', $sql);
                    $this->assertStringContainsString('overview_tsv tsvector GENERATED ALWAYS AS', $sql);

                    // Should NOT contain generic content_tsv
                    $this->assertStringNotContainsString('content_tsv tsvector GENERATED ALWAYS AS (to_tsvector(\'simple\', content)) STORED', $sql);
                } elseif ($callCount >= 8 && $callCount <= 9) {
                    // Verify separate GIN indexes
                    $this->assertStringContainsString('_tsv_idx', $sql);
                    $this->assertStringContainsString('USING gin(', $sql);
                }

                return 0;
            });

        $store->setup();
    }

    public function testFuzzyWeightParameter()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 0.4,
            fuzzyWeight: 0.5
        );

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                $this->assertStringContainsString('fuzzy_scores AS', $sql);
                $this->assertStringContainsString('combined_results AS', $sql);
                $this->assertStringContainsString('COALESCE(1.0 / (', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())->method('execute');
        $statement->expects($this->once())->method('fetchAll')->willReturn([]);

        iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'test', 0.4)));
    }

    public function testBoostFieldsApplied()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $rrf = new ReciprocalRankFusion(normalizeScores: false);
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 1.0, rrf: $rrf);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid1->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['text' => 'Popular', 'popularity' => 100]),
                    'score' => 0.5,
                ],
                [
                    'id' => $uuid2->toRfc4122(),
                    'embedding' => '[0.4,0.5,0.6]',
                    'metadata' => json_encode(['text' => 'Unpopular', 'popularity' => 10]),
                    'score' => 0.6,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), [
            'boostFields' => [
                'popularity' => ['min' => 50, 'boost' => 0.5],
            ],
        ]));

        $this->assertCount(2, $results);

        // First result should be boosted (popularity >= 50)
        // Original score 0.5 * 1.5 = 0.75
        $this->assertSame(0.75, $results[0]->getScore());
        $this->assertArrayHasKey('_applied_boosts', $results[0]->getMetadata()->getArrayCopy());

        // Second result should not be boosted (popularity < 50)
        $this->assertSame(0.6, $results[1]->getScore());
    }

    public function testScoreBreakdownIncluded()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $rrf = new ReciprocalRankFusion(normalizeScores: false);
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.5, rrf: $rrf);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['text' => 'Test']),
                    'score' => 0.025,
                    'vector_rank' => 1,
                    'fts_rank' => 2,
                    'vector_distance' => 0.1,
                    'fts_score' => 0.8,
                    'vector_contribution' => 0.015,
                    'fts_contribution' => 0.01,
                    'fuzzy_rank' => 3,
                    'fuzzy_score' => 0.7,
                    'fuzzy_contribution' => 0.005,
                ],
            ]);

        $results = iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'test', 0.5), [
            'includeScoreBreakdown' => true,
        ]));

        $this->assertCount(1, $results);

        $metadata = $results[0]->getMetadata()->getArrayCopy();
        $this->assertArrayHasKey('_score_breakdown', $metadata);

        $breakdown = $metadata['_score_breakdown'];
        $this->assertSame(1, $breakdown['vector_rank']);
        $this->assertSame(2, $breakdown['fts_rank']);
        $this->assertSame(3, $breakdown['fuzzy_rank']);
        $this->assertSame(0.1, $breakdown['vector_distance']);
        $this->assertSame(0.8, $breakdown['fts_score']);
        $this->assertSame(0.7, $breakdown['fuzzy_score']);
    }

    public function testNullVectorForFtsOnlyResults()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.0);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $uuid = Uuid::v4();

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => null,
                    'metadata' => json_encode(['text' => 'FTS only result']),
                    'score' => 0.5,
                ],
            ]);

        $results = iterator_to_array($store->query(new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'FTS', 0.0)));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(NullVector::class, $results[0]->getVector());
    }

    public function testScoreNormalization()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        // Enable normalization (default)
        $rrf = new ReciprocalRankFusion(k: 60, normalizeScores: true);
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 1.0, rrf: $rrf);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $uuid = Uuid::v4();

        // Raw RRF score
        $rawScore = 0.01639; // Approximately 1/(60+1) = theoretical max

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => $uuid->toRfc4122(),
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['text' => 'Test']),
                    'score' => $rawScore,
                ],
            ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $results);

        // Score should be normalized to approximately 100
        $expectedNormalized = $rrf->normalize($rawScore);
        $this->assertEqualsWithDelta($expectedNormalized, $results[0]->getScore(), 0.01);
    }

    private function normalizeQuery(string $query): string
    {
        $normalized = preg_replace('/\s+/', ' ', $query);

        return trim($normalized);
    }
}
