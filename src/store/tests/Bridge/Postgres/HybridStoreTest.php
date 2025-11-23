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
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Postgres\HybridStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
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

    public function testSetupCreatesTableWithFullTextSearchSupport()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new HybridStore($pdo, 'hybrid_table');

        $pdo->expects($this->exactly(9))
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
                    $this->assertStringContainsString('content_tsv tsvector GENERATED ALWAYS AS (to_tsvector(\'simple\', content)) STORED', $sql);
                } elseif (4 === $callCount) {
                    $this->assertStringContainsString('ALTER TABLE hybrid_table ADD COLUMN IF NOT EXISTS search_text TEXT', $sql);
                } elseif (5 === $callCount) {
                    $this->assertStringContainsString('CREATE OR REPLACE FUNCTION update_search_text()', $sql);
                } elseif (6 === $callCount) {
                    $this->assertStringContainsString('CREATE TRIGGER trigger_update_search_text', $sql);
                } elseif (7 === $callCount) {
                    $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS hybrid_table_embedding_idx', $sql);
                } elseif (8 === $callCount) {
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

        $statement->expects($this->once())
            ->method('execute')
            ->with([
                'id' => $uuid->toRfc4122(),
                'metadata' => json_encode(['_text' => 'Test content', 'category' => 'test']),
                'content' => 'Test content',
                'vector' => '[0.1,0.2,0.3]',
            ]);

        $metadata = new Metadata(['_text' => 'Test content', 'category' => 'test']);
        $document = new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), $metadata);
        $store->add($document);
    }

    public function testPureVectorSearch()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        // Disable score normalization for this test to keep legacy behavior
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 1.0, normalizeScores: false);

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

        $results = $store->query(new Vector([0.1, 0.2, 0.3]));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame(0.05, $results[0]->score);
    }

    public function testPureKeywordSearch()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        // Disable normalization for consistent test scores
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.0, normalizeScores: false);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Verify BM25 structure instead of FTS
                $this->assertStringContainsString('WITH', $sql);
                $this->assertStringContainsString('bm25_search AS', $sql);
                $this->assertStringContainsString('bm25topk(', $sql);
                $this->assertStringContainsString('bm25_with_metadata AS', $sql);
                $this->assertStringContainsString('DISTINCT ON (b.bm25_rank)', $sql);

                // Should NOT contain old FTS functions
                $this->assertStringNotContainsString('ts_rank_cd', $sql);
                $this->assertStringNotContainsString('websearch_to_tsquery', $sql);

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
                    'embedding' => '[0.1,0.2,0.3]',
                    'metadata' => json_encode(['text' => 'PostgreSQL is awesome']),
                    'score' => 0.5,
                ],
            ]);

        $results = $store->query(new Vector([0.1, 0.2, 0.3]), ['q' => 'PostgreSQL']);

        $this->assertCount(1, $results);
        $this->assertSame(0.5, $results[0]->score);
    }

    public function testHybridSearchWithRRF()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        // Disable normalization for consistent test scores
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.5, rrfK: 60, normalizeScores: false);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Check for RRF CTE structure with BM25 and fuzzy
                $this->assertStringContainsString('WITH vector_scores AS', $sql);
                $this->assertStringContainsString('bm25_search AS', $sql);
                $this->assertStringContainsString('bm25_with_metadata AS', $sql);
                $this->assertStringContainsString('fuzzy_scores AS', $sql);
                $this->assertStringContainsString('combined_results AS', $sql);
                $this->assertStringContainsString('ROW_NUMBER() OVER', $sql);
                $this->assertStringContainsString('FULL OUTER JOIN', $sql);
                $this->assertStringContainsString('ORDER BY score DESC', $sql);

                // Should NOT contain old fts_scores CTE
                $this->assertStringNotContainsString('fts_scores AS', $sql);

                // Should contain BM25 function call
                $this->assertStringContainsString('bm25topk(', $sql);

                // Should contain fuzzy matching
                $this->assertStringContainsString('word_similarity', $sql);

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

        $results = $store->query(new Vector([0.1, 0.2, 0.3]), ['q' => 'PostgreSQL', 'semanticRatio' => 0.5]);

        $this->assertCount(1, $results);
        $this->assertSame(0.025, $results[0]->score);
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

        $results = $store->query(new Vector([0.1, 0.2, 0.3]));

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

        $results = $store->query(new Vector([0.1, 0.2, 0.3]), ['maxScore' => 0.5]);

        $this->assertCount(0, $results);
    }

    public function testQueryWithCustomLanguage()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        // Test BM25 language parameter (short code 'fr' instead of 'french')
        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.0, language: 'french', bm25Language: 'fr');

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Should NOT contain old FTS function
                $this->assertStringNotContainsString("websearch_to_tsquery('french'", $sql);

                // Should contain BM25 with 'fr' language code
                $this->assertStringContainsString('bm25topk(', $sql);
                $this->assertStringContainsString("'fr'", $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $store->query(new Vector([0.1, 0.2, 0.3]), ['q' => 'développement']);
    }

    public function testQueryWithCustomRRFK()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        $store = new HybridStore($pdo, 'hybrid_table', semanticRatio: 0.5, rrfK: 100);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Check for RRF constant 100 in the formula
                $this->assertStringContainsString('100 + v.rank_ix', $sql);
                $this->assertStringContainsString('100 + b.bm25_rank', $sql);
                $this->assertStringContainsString('100 + fz.rank_ix', $sql);

                // Verify BM25 and fuzzy structure (not old FTS)
                $this->assertStringContainsString('bm25_search AS', $sql);
                $this->assertStringContainsString('fuzzy_scores AS', $sql);

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())
            ->method('execute');

        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $store->query(new Vector([0.1, 0.2, 0.3]), ['q' => 'test']);
    }

    public function testQueryInvalidSemanticRatioInOptions()
    {
        $pdo = $this->createMock(\PDO::class);
        $store = new HybridStore($pdo, 'hybrid_table');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The semantic ratio must be between 0.0 and 1.0');

        $store->query(new Vector([0.1, 0.2, 0.3]), ['semanticRatio' => 1.5]);
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

        $store->query(new Vector([0.1, 0.2, 0.3]), ['limit' => 10]);
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

        $statement->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $params) use ($uuid1, $uuid2): bool {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame($uuid1->toRfc4122(), $params['id']);
                    $this->assertSame('First document', $params['content']);
                } else {
                    $this->assertSame($uuid2->toRfc4122(), $params['id']);
                    $this->assertSame('Second document', $params['content']);
                }

                return true;
            });

        $metadata1 = new Metadata(['_text' => 'First document']);
        $metadata2 = new Metadata(['_text' => 'Second document']);

        $document1 = new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3]), $metadata1);
        $document2 = new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), $metadata2);

        $store->add($document1, $document2);
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

        $results = $store->query(new Vector([0.1, 0.2, 0.3]), ['q' => 'zzzzzzzzzzzzz']);

        $this->assertCount(0, $results);
    }

    public function testFuzzyMatchingWithWordSimilarity()
    {
        $pdo = $this->createMock(\PDO::class);
        $statement = $this->createMock(\PDOStatement::class);

        // Test fuzzy matching with custom thresholds
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
                $this->assertStringContainsString('0.300000', $sql); // Primary threshold
                $this->assertStringContainsString('0.250000', $sql); // Secondary threshold
                $this->assertStringContainsString('0.200000', $sql); // Strict threshold

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())->method('execute');
        $statement->expects($this->once())->method('fetchAll')->willReturn([]);

        $store->query(new Vector([0.1, 0.2, 0.3]), ['q' => 'test']);
    }

    public function testSearchableAttributesWithBoost()
    {
        $pdo = $this->createMock(\PDO::class);

        // Test with searchable attributes configuration
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

                    // Should NOT contain generic content_tsv (backward compat mode)
                    $this->assertStringNotContainsString('content_tsv tsvector GENERATED ALWAYS AS (to_tsvector(\'simple\', content)) STORED', $sql);
                } elseif ($callCount >= 8 && $callCount <= 9) {
                    // Verify separate GIN indexes for each attribute (title_tsv_idx, overview_tsv_idx)
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

        // Test that fuzzyWeight controls the weight in RRF formula
        $store = new HybridStore(
            $pdo,
            'hybrid_table',
            semanticRatio: 0.4,  // 60% non-semantic
            fuzzyWeight: 0.5     // 50% of non-semantic goes to fuzzy
        );
        // Expected: 40% vector, 30% BM25 (60% * 0.5), 30% fuzzy (60% * 0.5)

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($sql) {
                // Verify fuzzy weight is present in the RRF formula
                $this->assertStringContainsString('fuzzy_scores AS', $sql);
                $this->assertStringContainsString('combined_results AS', $sql);

                // Should have three components: vector, BM25, fuzzy
                $this->assertStringContainsString('COALESCE(1.0 / (', $sql); // RRF formula pattern

                return true;
            }))
            ->willReturn($statement);

        $statement->expects($this->once())->method('execute');
        $statement->expects($this->once())->method('fetchAll')->willReturn([]);

        $store->query(new Vector([0.1, 0.2, 0.3]), ['q' => 'test']);
    }

    private function normalizeQuery(string $query): string
    {
        // Remove extra spaces, tabs and newlines
        $normalized = preg_replace('/\s+/', ' ', $query);

        // Trim the result
        return trim($normalized);
    }
}
