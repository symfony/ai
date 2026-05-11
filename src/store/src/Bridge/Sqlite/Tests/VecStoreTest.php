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
use Symfony\AI\Store\Bridge\Sqlite\Distance;
use Symfony\AI\Store\Bridge\Sqlite\VecStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;

final class VecStoreTest extends TestCase
{
    public function testFromDbalWithNonPdoDriverThrows()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn(new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only DBAL connections using PDO driver are supported.');

        VecStore::fromDbal($connection, 'test_vectors');
    }

    public function testSupportsAllQueryTypes()
    {
        $store = new VecStore(new \PDO('sqlite::memory:'), 'test_vectors');

        $this->assertTrue($store->supports(VectorQuery::class));
        $this->assertTrue($store->supports(TextQuery::class));
        $this->assertTrue($store->supports(HybridQuery::class));
    }

    public function testSupportsDoesNotSupportUnknownQueryType()
    {
        $store = new VecStore(new \PDO('sqlite::memory:'), 'test_vectors');

        $unknownQuery = new class implements \Symfony\AI\Store\Query\QueryInterface {};

        $this->assertFalse($store->supports($unknownQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $this->assertSame(0, $store->count());

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3])),
            new VectorDocument('doc-2', new Vector([0.4, 0.5, 0.6])),
        ]);

        $this->assertSame(2, $store->count());

        $store->remove('doc-1');

        $this->assertSame(1, $store->count());
    }

    public function testRemoveWithEmptyArray()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'test'])));
        $store->remove([]);

        // Document should still be there
        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxItems' => 10]));
        $this->assertCount(1, $results);
    }

    public function testSetup()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        // Verify vec0 table exists
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors'");
        $this->assertSame('test_vectors', $result->fetchColumn());

        // Verify FTS table exists
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors_fts'");
        $this->assertSame('test_vectors_fts', $result->fetchColumn());
    }

    public function testSetupWithUnsupportedOptions()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $this->expectException(InvalidArgumentException::class);

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup(['unsupported' => true]);
    }

    public function testDrop()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $metadata = new Metadata(['name' => 'test']);
        $metadata->setText('Some text content');

        $store->add(new VectorDocument(
            id: 'doc-1',
            vector: new Vector([0.1, 0.2, 0.3]),
            metadata: $metadata,
        ));

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxItems' => 10]));
        $this->assertCount(1, $results);

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(1, (int) $result->fetchColumn());
    }

    public function testAddMultipleDocuments()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.4, 0.5, 0.6]), new Metadata(['name' => 'second'])),
        ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxItems' => 10]));
        $this->assertCount(2, $results);
    }

    public function testAddDuplicateDocumentUpdates()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $metadata1 = new Metadata(['name' => 'original']);
        $metadata1->setText('Original text');

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), $metadata1));

        $metadata2 = new Metadata(['name' => 'updated']);
        $metadata2->setText('Updated text');

        $store->add(new VectorDocument('doc-1', new Vector([0.4, 0.5, 0.6]), $metadata2));

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.4, 0.5, 0.6])), ['maxItems' => 10]));
        $this->assertCount(1, $results);
        $this->assertSame('updated', $results[0]->getMetadata()['name']);

        // FTS should also be updated
        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(1, (int) $result->fetchColumn());
    }

    public function testQueryVector()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.0, 1.0, 0.0]), new Metadata(['name' => 'second'])),
            new VectorDocument('doc-3', new Vector([0.0, 0.0, 1.0]), new Metadata(['name' => 'third'])),
        ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])), ['maxItems' => 10]));

        $this->assertCount(3, $results);
        $this->assertSame('doc-1', $results[0]->getId());
    }

    public function testQueryVectorWithMaxItems()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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

    public function testQueryVectorWithEmptyStore()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0]))));

        $this->assertCount(0, $results);
    }

    public function testQueryText()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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

    public function testQueryHybrid()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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

        // RRF with equal ratio: doc-1 (vector rank 1) and doc-3 (text rank 1) should both appear
        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), 'neural networks', 0.5),
        ));

        $this->assertNotEmpty($results);

        $foundIds = array_map(static fn (VectorDocument $doc): string|int => $doc->getId(), $results);

        $this->assertContains('doc-1', $foundIds, 'RRF should include the top vector match');
        $this->assertContains('doc-3', $foundIds, 'RRF should include the top text match');

        // All results should have RRF scores
        foreach ($results as $result) {
            $this->assertNotNull($result->getScore());
            $this->assertGreaterThan(0.0, $result->getScore());
        }

        // Results should be sorted by RRF score descending
        $scores = array_map(static fn (VectorDocument $doc): float => $doc->getScore(), $results);
        $sortedScores = $scores;
        rsort($sortedScores);
        $this->assertSame($sortedScores, $scores, 'Results should be sorted by RRF score descending');
    }

    public function testQueryHybridScoresAreNeverNull()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('unrelated content here');

        $metadata2 = new Metadata();
        $metadata2->setText('keyword appears here');

        $store->add([
            new VectorDocument('doc-vector', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata2),
        ]);

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
        ));

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
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('keyword relevant');

        $metadata2 = new Metadata();
        $metadata2->setText('no match');

        $metadata3 = new Metadata();
        $metadata3->setText('keyword here');

        $store->add([
            new VectorDocument('doc-both', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-vector', new Vector([0.99, 0.1, 0.0]), $metadata2),
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata3),
        ]);

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
        ));

        $this->assertCount(3, $results);
        $this->assertSame('doc-both', $results[0]->getId());
    }

    public function testQueryHybridRespectsMaxItems()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('unrelated content here');

        $metadata2 = new Metadata();
        $metadata2->setText('keyword match');

        $store->add([
            new VectorDocument('doc-vector', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata2),
        ]);

        $scoreAt09 = $this->getScoreForDoc('doc-vector', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.9));
        $scoreAt01 = $this->getScoreForDoc('doc-vector', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.1));

        $this->assertGreaterThan($scoreAt01, $scoreAt09);
    }

    public function testQueryHybridLowSemanticRatioScoresTextDocHigher()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $metadata1 = new Metadata();
        $metadata1->setText('unrelated content here');

        $metadata2 = new Metadata();
        $metadata2->setText('keyword match');

        $store->add([
            new VectorDocument('doc-vector', new Vector([1.0, 0.0, 0.0]), $metadata1),
            new VectorDocument('doc-text', new Vector([0.0, 0.0, 1.0]), $metadata2),
        ]);

        $scoreAt09 = $this->getScoreForDoc('doc-text', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.9));
        $scoreAt01 = $this->getScoreForDoc('doc-text', $store, new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.1));

        $this->assertGreaterThan($scoreAt09, $scoreAt01);
    }

    public function testQueryHybridWithRrfCandidatePoolSize()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
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
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([1.0, 0.0, 0.0]), ['keyword'], 0.5),
        ));

        $this->assertCount(0, $results);
    }

    public function testRemoveWithSingleId()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $metadata = new Metadata(['name' => 'test']);
        $metadata->setText('Some text');

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), $metadata));
        $store->remove('doc-1');

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
        $this->assertCount(0, $results);

        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(0, (int) $result->fetchColumn());
    }

    public function testRemoveWithMultipleIds()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.4, 0.5, 0.6]), new Metadata(['name' => 'second'])),
            new VectorDocument('doc-3', new Vector([0.7, 0.8, 0.9]), new Metadata(['name' => 'third'])),
        ]);

        $store->remove(['doc-1', 'doc-3']);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.4, 0.5, 0.6])), ['maxItems' => 10]));
        $this->assertCount(1, $results);
        $this->assertSame('doc-2', $results[0]->getId());
    }

    public function testUnsupportedQueryTypeThrows()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $query = new class implements \Symfony\AI\Store\Query\QueryInterface {};

        $this->expectException(UnsupportedQueryTypeException::class);

        iterator_to_array($store->query($query));
    }

    public function testFromPdo()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = VecStore::fromPdo($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors'");
        $this->assertSame('test_vectors', $result->fetchColumn());
    }

    public function testFromDbalWithPdoDriver()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn($pdo);

        $store = VecStore::fromDbal($connection, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_vectors'");
        $this->assertSame('test_vectors', $result->fetchColumn());
    }

    public function testAddDocumentWithoutText()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
        $store->setup();

        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3]), new Metadata(['name' => 'no-text'])));

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['maxItems' => 10]));
        $this->assertCount(1, $results);

        // No FTS entry since no text metadata
        $result = $pdo->query('SELECT COUNT(*) FROM test_vectors_fts');
        $this->assertSame(0, (int) $result->fetchColumn());
    }

    public function testQueryVectorWithL2Distance()
    {
        $pdo = $this->createPdo();

        if (!VecStore::isExtensionAvailable($pdo)) {
            $this->markTestSkipped('sqlite-vec extension is not available.');
        }

        $store = new VecStore($pdo, 'test_vectors', Distance::L2, 3);
        $store->setup();

        $store->add([
            new VectorDocument('doc-1', new Vector([1.0, 0.0, 0.0]), new Metadata(['name' => 'first'])),
            new VectorDocument('doc-2', new Vector([0.0, 1.0, 0.0]), new Metadata(['name' => 'second'])),
            new VectorDocument('doc-3', new Vector([0.0, 0.0, 1.0]), new Metadata(['name' => 'third'])),
        ]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])), ['maxItems' => 10]));

        $this->assertCount(3, $results);
        $this->assertSame('doc-1', $results[0]->getId());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getScoreForDoc(string $id, VecStore $store, HybridQuery $query, array $options = []): float
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
        $extensionPath = $_SERVER['SQLITE_VEC_PATH'] ?? $_ENV['SQLITE_VEC_PATH'] ?? null;

        if (null !== $extensionPath && file_exists($extensionPath) && \PHP_VERSION_ID >= 80400) {
            $pdo = new \Pdo\Sqlite('sqlite::memory:');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->loadExtension($extensionPath);

            return $pdo;
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
