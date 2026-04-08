<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Sqlite\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Sqlite\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;

final class StoreTest extends TestCase
{
    public function testSetup()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        // Verify main table exists
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors'");
        $this->assertSame('test_vectors', $result->fetchColumn());

        // Verify FTS table exists
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors_fts'");
        $this->assertSame('test_vectors_fts', $result->fetchColumn());
    }

    public function testSetupWithUnsupportedOptions()
    {
        $this->expectException(InvalidArgumentException::class);

        $store = $this->createStore();
        $store->setup(['unsupported' => true]);
    }

    public function testDrop()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();
        $store->drop();

        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors'");
        $this->assertFalse($result->fetchColumn());

        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors_fts'");
        $this->assertFalse($result->fetchColumn());
    }

    public function testAddSingleDocument()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        $metadata = new Metadata(['name' => 'test']);
        $metadata->setText('Some text content');

        $store->add(new VectorDocument(
            id: 'doc-1',
            vector: new Vector([0.1, 0.2, 0.3]),
            metadata: $metadata,
        ));

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors');
        $this->assertSame(1, (int) $result->fetchColumn());

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(1, (int) $result->fetchColumn());
    }

    public function testAddMultipleDocuments()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.4, 0.5, 0.6]), new Metadata(['name' => 'second'])),
        ]);

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors');
        $this->assertSame(2, (int) $result->fetchColumn());
    }

    public function testAddDuplicateDocumentUpdates()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        $metadata1 = new Metadata(['name' => 'original']);
        $metadata1->setText('Original text');

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), $metadata1));

        $metadata2 = new Metadata(['name' => 'updated']);
        $metadata2->setText('Updated text');

        $store->add(new VectorDocument('doc-1', new Vector([0.4, 0.5, 0.6]), $metadata2));

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors');
        $this->assertSame(1, (int) $result->fetchColumn());

        $result = $pdo->query("SELECT metadata FROM test_vectors WHERE id = 'doc-1'");
        $metadata = json_decode($result->fetchColumn(), true);
        $this->assertSame('updated', $metadata['name']);

        // FTS should also be updated (old deleted, new inserted)
        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(1, (int) $result->fetchColumn());
    }

    public function testRemoveWithSingleId()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        $metadata = new Metadata(['name' => 'test']);
        $metadata->setText('Some text');

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), $metadata));
        $store->remove('doc-1');

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors');
        $this->assertSame(0, (int) $result->fetchColumn());

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(0, (int) $result->fetchColumn());
    }

    public function testRemoveWithMultipleIds()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.4, 0.5, 0.6]), new Metadata(['name' => 'second'])),
            new VectorDocument('doc-3', new Vector([0.7, 0.8, 0.9]), new Metadata(['name' => 'third'])),
        ]);

        $store->remove(['doc-1', 'doc-3']);

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors');
        $this->assertSame(1, (int) $result->fetchColumn());

        $result = $pdo->query('SELECT id FROM test_vectors');
        $this->assertSame('doc-2', $result->fetchColumn());
    }

    public function testRemoveWithEmptyArray()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'test'])));
        $store->remove([]);

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors');
        $this->assertSame(1, (int) $result->fetchColumn());
    }

    public function testQueryVector()
    {
        $store = $this->createStore();
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.0, 1.0, 0.0]), new Metadata(['name' => 'second'])),
            new VectorDocument('doc-3', new Vector([0.0, 0.0, 1.0]), new Metadata(['name' => 'third'])),
        ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0]))));

        $this->assertCount(3, $results);
        $this->assertSame('doc-1', $results[0]->getId());
    }

    public function testQueryVectorWithMaxItems()
    {
        $store = $this->createStore();
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.0, 1.0, 0.0]), new Metadata(['name' => 'second'])),
            new VectorDocument('doc-3', new Vector([0.0, 0.0, 1.0]), new Metadata(['name' => 'third'])),
        ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])), ['maxItems' => 2]));

        $this->assertCount(2, $results);
    }

    public function testQueryVectorWithFilter()
    {
        $store = $this->createStore();
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), new Metadata(['category' => 'a'])),
            new VectorDocument('doc-2', new Vector([0.9, 0.1, 0.0]), new Metadata(['category' => 'b'])),
            new VectorDocument('doc-3', new Vector([0.8, 0.2, 0.0]), new Metadata(['category' => 'a'])),
        ]);

        $results = iterator_to_array($store->query(
            new VectorQuery(new Vector([1.0, 0.0, 0.0])),
            ['filter' => static fn (VectorDocument $doc): bool => 'a' === $doc->getMetadata()['category']],
        ));

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertSame('a', $result->getMetadata()['category']);
        }
    }

    public function testQueryText()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata(['name' => 'first']);
        $metadata1->setText('The quick brown fox jumps over the lazy dog');

        $metadata2 = new Metadata(['name' => 'second']);
        $metadata2->setText('Machine learning and artificial intelligence are transforming technology');

        $metadata3 = new Metadata(['name' => 'third']);
        $metadata3->setText('The lazy cat sleeps all day');

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), $metadata1),
            new VectorDocument('doc-2', new Vector([0.4, 0.5, 0.6]), $metadata2),
            new VectorDocument('doc-3', new Vector([0.7, 0.8, 0.9]), $metadata3),
        ]);

        $results = iterator_to_array($store->query(new TextQuery('artificial intelligence')));

        $this->assertCount(1, $results);
        $this->assertSame('doc-2', $results[0]->getId());
    }

    public function testQueryTextMultipleTerms()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata(['name' => 'first']);
        $metadata1->setText('The quick brown fox jumps');

        $metadata2 = new Metadata(['name' => 'second']);
        $metadata2->setText('Machine learning is great');

        $metadata3 = new Metadata(['name' => 'third']);
        $metadata3->setText('The fox is lazy');

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), $metadata1),
            new VectorDocument('doc-2', new Vector([0.4, 0.5, 0.6]), $metadata2),
            new VectorDocument('doc-3', new Vector([0.7, 0.8, 0.9]), $metadata3),
        ]);

        $results = iterator_to_array($store->query(new TextQuery(['fox', 'learning'])));

        $this->assertGreaterThanOrEqual(2, \count($results));
    }

    public function testQueryTextNoResults()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata = new Metadata(['name' => 'test']);
        $metadata->setText('The quick brown fox');

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), $metadata));

        $results = iterator_to_array($store->query(new TextQuery('nonexistent')));

        $this->assertCount(0, $results);
    }

    public function testQueryHybrid()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata(['name' => 'first']);
        $metadata1->setText('Vectors and embeddings in machine learning');

        $metadata2 = new Metadata(['name' => 'second']);
        $metadata2->setText('Database indexing and search optimization');

        $metadata3 = new Metadata(['name' => 'third']);
        $metadata3->setText('Artificial intelligence and neural networks');

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-2', new Vector([0.0, 1.0, 0.0]), $metadata2),
            new VectorDocument('doc-3', new Vector([0.0, 0.0, 1.0]), $metadata3),
        ]);

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), 'neural networks', 0.5),
        ));

        $this->assertNotEmpty($results);

        $foundIds = array_map(static fn (VectorDocument $doc): string|int => $doc->getId(), $results);

        // Should find doc-1 (vector match) and/or doc-3 (text match)
        $this->assertTrue(
            \in_array('doc-1', $foundIds, true) || \in_array('doc-3', $foundIds, true),
            'HybridQuery should find either document 1 (vector match) or document 3 (text match)',
        );
    }

    public function testQueryHybridScoresAreNeverNull()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('vector match only');

        $metadata2 = new Metadata();
        $metadata2->setText('keyword match here');

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-2', new Vector([0.0, 1.0, 0.0]), $metadata2),
        ]);

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
        ));

        foreach ($results as $doc) {
            $this->assertNotNull($doc->getScore(), 'Hybrid query score must never be null');
            $this->assertGreaterThan(0.0, $doc->getScore());
        }
    }

    public function testQueryHybridWithNoOverlapBetweenVectorAndTextResults()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('unrelated content here');

        $metadata2 = new Metadata();
        $metadata2->setText('keyword appears here');

        $store->add([
            // Vector-close doc, no text match
            new VectorDocument('doc-vector', new Vector([1.0, 0.0, 0.0]), $metadata1),
            // Text-match doc, distant vector
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata2),
        ]);

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
        ));

        // Both documents must appear and have non-null scores
        $this->assertCount(2, $results);

        $ids = array_map(static fn (VectorDocument $doc): string|int => $doc->getId(), $results);
        $this->assertContains('doc-vector', $ids);
        $this->assertContains('doc-text', $ids);

        foreach ($results as $doc) {
            $this->assertNotNull($doc->getScore());
            $this->assertGreaterThan(0.0, $doc->getScore());
        }
    }

    public function testQueryHybridDocumentInBothListsRanksHigher()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('keyword relevant');

        $metadata2 = new Metadata();
        $metadata2->setText('no match');

        $metadata3 = new Metadata();
        $metadata3->setText('keyword here');

        $store->add([
            // Matches both: close vector AND contains the keyword
            new VectorDocument('doc-both', new Vector([1.0, 0.0, 0.0]), $metadata1),
            // Close vector but no text match
            new VectorDocument('doc-vector', new Vector([0.99, 0.1, 0.0]), $metadata2),
            // Distant vector but text match
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata3),
        ]);

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
        ));

        $this->assertCount(3, $results);

        // The document appearing in both lists must have the highest RRF score
        $this->assertSame('doc-both', $results[0]->getId());
    }

    public function testQueryHybridRespectsMaxItems()
    {
        $store = $this->createStore();
        $store->setup();

        for ($i = 1; $i <= 10; ++$i) {
            $metadata = new Metadata();
            $metadata->setText(\sprintf('document number %d keyword', $i));
            $store->add(new VectorDocument(
                \sprintf('doc-%d', $i),
                new Vector([1.0, 0.0, 0.0]),
                $metadata,
            ));
        }

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
            ['maxItems' => 3],
        ));

        $this->assertCount(3, $results);
    }

    public function testQueryHybridHighSemanticRatioScoresVectorDocHigher()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('unrelated content here');

        $metadata2 = new Metadata();
        $metadata2->setText('keyword match');

        $store->add([
            // Vector-only doc (no text match): score depends entirely on semantic ratio
            new VectorDocument('doc-vector', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata2),
        ]);

        $scoreAt09 = $this->getScoreForDoc('doc-vector', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.9));
        $scoreAt01 = $this->getScoreForDoc('doc-vector', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.1));

        // Higher semantic ratio → higher RRF weight on vector contribution → higher score for vector-only doc
        $this->assertGreaterThan($scoreAt01, $scoreAt09);
    }

    public function testQueryHybridLowSemanticRatioScoresTextDocHigher()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('unrelated content here');

        $metadata2 = new Metadata();
        $metadata2->setText('keyword match');

        $store->add([
            // Push doc-text to vector rank 1 so its text contribution is the variable
            new VectorDocument('doc-vector', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata2),
        ]);

        $scoreAt09 = $this->getScoreForDoc('doc-text', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.9));
        $scoreAt01 = $this->getScoreForDoc('doc-text', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.1));

        // Lower semantic ratio (= higher keyword ratio) → higher RRF weight on text contribution
        $this->assertGreaterThan($scoreAt09, $scoreAt01);
    }

    public function testQueryHybridRespectsFilter()
    {
        $store = $this->createStore();
        $store->setup();

        $metadata1 = new Metadata(['category' => 'a']);
        $metadata1->setText('keyword match');

        $metadata2 = new Metadata(['category' => 'b']);
        $metadata2->setText('keyword match');

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-2', new Vector([0.9, 0.1, 0.0]), $metadata2),
        ]);

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
            ['filter' => static fn (VectorDocument $doc): bool => 'a' === $doc->getMetadata()['category']],
        ));

        $this->assertCount(1, $results);
        $this->assertSame('doc-1', $results[0]->getId());
    }

    public function testQueryHybridWithRrfCandidatePoolSize()
    {
        $store = $this->createStore();
        $store->setup();

        for ($i = 1; $i <= 10; ++$i) {
            $metadata = new Metadata();
            $metadata->setText(\sprintf('document number %d keyword', $i));
            $store->add(new VectorDocument(
                \sprintf('doc-%d', $i),
                new Vector([1.0, 0.0, 0.0]),
                $metadata,
            ));
        }

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
            ['maxItems' => 3, 'rrfCandidatePoolSize' => 5],
        ));

        $this->assertCount(3, $results);

        foreach ($results as $doc) {
            $this->assertNotNull($doc->getScore());
        }
    }

    public function testQueryHybridWithEmptyStore()
    {
        $store = $this->createStore();
        $store->setup();

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
        ));

        $this->assertCount(0, $results);
    }

    public function testSupportsAllQueryTypes()
    {
        $store = $this->createStore();

        $this->assertTrue($store->supports(VectorQuery::class));
        $this->assertTrue($store->supports(TextQuery::class));
        $this->assertTrue($store->supports(HybridQuery::class));
    }

    public function testUnsupportedQueryTypeThrows()
    {
        $store = $this->createStore();
        $store->setup();

        $query = new class implements \Symfony\AI\Store\Query\QueryInterface {};

        $this->expectException(UnsupportedQueryTypeException::class);

        iterator_to_array($store->query($query));
    }

    public function testFromPdo()
    {
        $pdo = $this->createPdo();
        $store = Store::fromPdo($pdo, 'test_vectors');

        $store->setup();

        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors'");
        $this->assertSame('test_vectors', $result->fetchColumn());
    }

    public function testFromDbalWithPdoDriver()
    {
        $pdo = $this->createPdo();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn($pdo);

        $store = Store::fromDbal($connection, 'test_vectors');
        $store->setup();

        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors'");
        $this->assertSame('test_vectors', $result->fetchColumn());
    }

    public function testFromDbalWithNonPdoDriverThrows()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn(new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only DBAL connections using PDO driver are supported.');

        Store::fromDbal($connection, 'test_vectors');
    }

    public function testQueryVectorWithEmptyStore()
    {
        $store = $this->createStore();
        $store->setup();

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0]))));

        $this->assertCount(0, $results);
    }

    public function testAddDocumentWithoutText()
    {
        $pdo = $this->createPdo();
        $store = new Store($pdo, 'test_vectors');
        $store->setup();

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'no-text'])));

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors');
        $this->assertSame(1, (int) $result->fetchColumn());

        // No FTS entry since no text metadata
        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(0, (int) $result->fetchColumn());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getScoreForDoc(string $id, Store $store, HybridQuery $query, array $options = []): float
    {
        foreach ($store->query($query, $options) as $doc) {
            if ($doc->getId() === $id) {
                return $doc->getScore() ?? 0.0;
            }
        }

        $this->fail(\sprintf('Document %s not found in query results', $id));
    }

    private function createPdo(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function createStore(?\PDO $pdo = null): Store
    {
        return new Store($pdo ?? $this->createPdo(), 'test_vectors');
    }
}
