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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractStoreIntegrationTestCase extends TestCase
{
    private const DOCUMENT_ID_1 = '367e550e-6c92-4f12-8a6b-3f3f1d5e8c9a';
    private const DOCUMENT_ID_2 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const DOCUMENT_ID_3 = '123e4567-e89b-12d3-a456-426614174000';

    private static ?StoreInterface $store = null;

    public function testSetupStore()
    {
        self::$store = static::createStore();

        if (!self::$store instanceof ManagedStoreInterface) {
            $this->markTestSkipped('Store does not implement ManagedStoreInterface.');
        }

        self::$store->setup(static::getSetupOptions());

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
        if (!self::$store->supports(TextQuery::class)) {
            $this->markTestSkipped('Store does not support TextQuery.');
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
            $this->markTestSkipped('Store does not support HybridQuery.');
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
            $this->markTestSkipped('Store does not support remove().');
        }

        $this->waitForIndexing();

        $results = self::$store->query(new VectorQuery(new Vector([0.0, 0.0, 1.0])));

        foreach ($results as $result) {
            $this->assertNotSame(self::DOCUMENT_ID_3, $result->getId());
        }
    }

    #[Depends('testRemoveDocuments')]
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

    protected function waitForIndexing(): void
    {
        // no-op by default, override in subclasses that need indexing time
    }
}
