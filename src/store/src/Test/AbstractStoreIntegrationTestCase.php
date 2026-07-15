<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Test;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\UnsupportedFeatureException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractStoreIntegrationTestCase extends TestCase
{
    private const WRITE_CHUNK_SIZE = 500;

    private const DOCUMENT_ID_1 = '367e550e-6c92-4f12-8a6b-3f3f1d5e8c9a';
    private const DOCUMENT_ID_2 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const DOCUMENT_ID_3 = '123e4567-e89b-12d3-a456-426614174000';

    private static ?StoreInterface $store = null;

    public function testSetupStore()
    {
        self::$store = static::createStore();

        // this test must not be skipped, since the tests depending on it would be skipped as well, and
        // a store provisioning its schema outside of setup() would never get tested at all
        if (self::$store instanceof ManagedStoreInterface) {
            self::$store->setup(static::getSetupOptions());
        }

        $this->addToAssertionCount(1);
    }

    #[Depends('testSetupStore')]
    public function testAddDocuments()
    {
        // Add single document with text metadata
        $metadata1 = new Metadata(['name' => 'first document']);
        $metadata1->setText('This is the first document about vectors and embeddings');

        self::$store->add(new VectorDocument(
            self::DOCUMENT_ID_1,
            new Vector([1.0, 0.0, 0.0]),
            $metadata1
        ));

        // Add multiple documents with text metadata
        $metadata2 = new Metadata(['name' => 'second document']);
        $metadata2->setText('This is the second document about machine learning');

        $metadata3 = new Metadata(['name' => 'third document']);
        $metadata3->setText('This is the third document about artificial intelligence');

        self::$store->add([
            new VectorDocument(
                self::DOCUMENT_ID_2,
                new Vector([0.0, 1.0, 0.0]),
                $metadata2
            ),
            new VectorDocument(
                self::DOCUMENT_ID_3,
                new Vector([0.0, 0.0, 1.0]),
                $metadata3
            ),
        ]);

        $this->waitForIndexing();

        $this->addToAssertionCount(1);
    }

    #[Depends('testAddDocuments')]
    public function testQueryDocuments()
    {
        $results = self::$store->query(new VectorQuery(new Vector([0.0, 0.0, 1.0])));

        $found = null;
        foreach ($results as $result) {
            if (self::DOCUMENT_ID_3 === $result->getId()) {
                $found = $result;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertSame('third document', $found->getMetadata()['name']);
    }

    #[Depends('testAddDocuments')]
    public function testQueryDocumentsWithTextQuery()
    {
        // this test must not be skipped, since the tests depending on it would be skipped as well,
        // and the store would never get to the point of being removed from, cleared or dropped
        if (!self::$store->supports(TextQuery::class)) {
            $this->addToAssertionCount(1);

            return;
        }

        // Search for text that should match the third document
        $results = self::$store->query(new TextQuery('artificial intelligence'));

        $found = null;
        foreach ($results as $result) {
            if (self::DOCUMENT_ID_3 === $result->getId()) {
                $found = $result;
                break;
            }
        }

        $this->assertNotNull($found, 'Document 3 should be found by TextQuery');
        $this->assertSame('third document', $found->getMetadata()['name']);
        $this->assertStringContainsString('artificial intelligence', $found->getMetadata()->getText());
    }

    #[Depends('testAddDocuments')]
    public function testQueryDocumentsWithHybridQuery()
    {
        if (!self::$store->supports(HybridQuery::class)) {
            $this->addToAssertionCount(1);

            return;
        }

        // Hybrid query combining vector [0,0,1] (matches doc 3) with text "machine learning" (matches doc 2)
        $results = self::$store->query(
            new HybridQuery(
                new Vector([0.0, 0.0, 1.0]),
                'machine learning',
                0.5 // 50/50 semantic vs keyword ratio
            )
        );

        // Should find at least document 2 (text match) or document 3 (vector match)
        $foundIds = [];
        foreach ($results as $result) {
            $foundIds[] = $result->getId();
        }

        $this->assertNotEmpty($foundIds, 'HybridQuery should return results');

        // At least one of the relevant documents should be found
        $this->assertTrue(
            \in_array(self::DOCUMENT_ID_2, $foundIds, true) || \in_array(self::DOCUMENT_ID_3, $foundIds, true),
            'HybridQuery should find either document 2 (text match) or document 3 (vector match)'
        );
    }

    #[Depends('testQueryDocuments')]
    #[Depends('testQueryDocumentsWithTextQuery')]
    #[Depends('testQueryDocumentsWithHybridQuery')]
    public function testRemoveDocuments()
    {
        try {
            self::$store->remove(self::DOCUMENT_ID_3);
        } catch (UnsupportedFeatureException) {
            // this test must not be skipped, since the tests depending on it would be skipped as well,
            // and the store would never get to the point of being cleared or dropped
            $this->addToAssertionCount(1);

            return;
        }

        $this->waitForIndexing();

        $results = self::$store->query(new VectorQuery(new Vector([0.0, 0.0, 1.0])));

        foreach ($results as $result) {
            $this->assertNotSame(self::DOCUMENT_ID_3, $result->getId());
        }
    }

    #[Depends('testRemoveDocuments')]
    public function testClearStore()
    {
        self::$store->clear();

        $this->waitForIndexing();

        $results = self::$store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])));

        $this->assertCount(0, iterator_to_array($results, false));
    }

    #[Depends('testClearStore')]
    public function testStoreIsReusableAfterClear()
    {
        // in contrast to drop(), clear() keeps the underlying table/index/collection intact,
        // so documents can be added and queried again without another setup() call
        self::$store->add(new VectorDocument(
            self::DOCUMENT_ID_1,
            new Vector([1.0, 0.0, 0.0]),
            new Metadata(['name' => 'document after clear'])
        ));

        $this->waitForIndexing();

        $results = self::$store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])));

        $found = null;
        foreach ($results as $result) {
            if (self::DOCUMENT_ID_1 === $result->getId()) {
                $found = $result;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertSame('document after clear', $found->getMetadata()['name']);
    }

    #[Depends('testStoreIsReusableAfterClear')]
    public function testClearRemovesDocumentsBeyondASingleBatch()
    {
        $documentCount = static::getVolumeDocumentCount();

        // written in chunks, so the high volume tier below does not turn the whole set into one huge request
        for ($written = 0; $written < $documentCount; $written += self::WRITE_CHUNK_SIZE) {
            $documents = [];
            for ($i = $written; $i < min($written + self::WRITE_CHUNK_SIZE, $documentCount); ++$i) {
                $documents[] = new VectorDocument(
                    Uuid::v4()->toRfc4122(),
                    new Vector([1.0, $i / $documentCount, 0.0]),
                    new Metadata(['name' => \sprintf('bulk document %d', $i)])
                );
            }

            self::$store->add($documents);
        }

        $this->waitForIndexing();

        // guard against a vacuous assertion: clearing an already-empty store would pass the check below
        $results = self::$store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])));

        $this->assertNotCount(0, iterator_to_array($results, false));

        self::$store->clear(static::getClearOptions());

        $this->waitForIndexing();

        $results = self::$store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])));

        $this->assertCount(0, iterator_to_array($results, false));
    }

    #[Depends('testClearRemovesDocumentsBeyondASingleBatch')]
    public function testDropStore()
    {
        if (!self::$store instanceof ManagedStoreInterface) {
            $this->markTestSkipped('Store does not implement ManagedStoreInterface.');
        }

        self::$store->drop();

        $this->addToAssertionCount(1);
    }

    abstract protected static function createStore(): StoreInterface;

    /**
     * @return array<string, mixed>
     */
    protected static function getSetupOptions(): array
    {
        return [];
    }

    /**
     * Number of documents written by testClearRemovesDocumentsBeyondASingleBatch().
     *
     * High enough to exceed the result limits engines apply by default, so a clear() that only wipes
     * the first page of documents gets caught, but still cheap enough to run on every pull request.
     */
    protected static function getVolumeDocumentCount(): int
    {
        if (self::isHighVolumeRun()) {
            return static::getHighVolumeDocumentCount();
        }

        return 250;
    }

    /**
     * Number of documents written once STORE_HIGH_VOLUME_TESTS=1 is set.
     *
     * Engines also truncate bulk operations at hard server-side limits, which sit far above what a
     * per-pull-request run can afford to write. Stores whose clear() has to work around such a limit
     * should raise this until it exceeds theirs, and get covered by the scheduled high volume build.
     */
    protected static function getHighVolumeDocumentCount(): int
    {
        return 10_000;
    }

    protected static function isHighVolumeRun(): bool
    {
        return '1' === getenv('STORE_HIGH_VOLUME_TESTS');
    }

    /**
     * Options passed to clear() by testClearRemovesDocumentsBeyondASingleBatch().
     *
     * Stores paginating their clear() should lower "batch_size" here, so the volume test above forces
     * several iterations instead of wiping everything in one round-trip.
     *
     * @return array<string, mixed>
     */
    protected static function getClearOptions(): array
    {
        return [];
    }

    protected function waitForIndexing(): void
    {
        // no-op by default, override in subclasses that need indexing time
    }
}
