<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Filter\TextContainsFilter;
use Symfony\AI\Store\Document\FilterInterface;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;

final class IndexerTest extends TestCase
{
    public function testIndexSingleDocument()
    {
        $document = new TextDocument($id = Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore());
        $indexer->index();

        $this->assertCount(1, $store->documents);
        $this->assertInstanceOf(VectorDocument::class, $store->documents[0]);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
    }

    public function testIndexEmptyDocumentList()
    {
        $loader = new InMemoryLoader([]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore());
        $indexer->index();

        $this->assertSame([], $store->documents);
    }

    public function testIndexDocumentWithMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $document = new TextDocument($id = Uuid::v4(), 'Test content', $metadata);
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore());
        $indexer->index();

        $this->assertSame(1, $store->addCalls);
        $this->assertCount(1, $store->documents);
        $this->assertInstanceOf(VectorDocument::class, $store->documents[0]);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
        $this->assertSame(['key' => 'value'], $store->documents[0]->metadata->getArrayCopy());
    }

    public function testIndexWithSource()
    {
        $document1 = new TextDocument(Uuid::v4(), 'Document 1');
        $vector = new Vector([0.1, 0.2, 0.3]);

        // InMemoryLoader doesn't use source parameter, but we test that source is passed correctly
        $loader = new InMemoryLoader([$document1]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore());
        $indexer->index('source1');

        $this->assertCount(1, $store->documents);
    }

    public function testIndexWithSourceArray()
    {
        $document1 = new TextDocument(Uuid::v4(), 'Document 1');
        $document2 = new TextDocument(Uuid::v4(), 'Document 2');
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $vector3 = new Vector([0.7, 0.8, 0.9]);
        $vector4 = new Vector([1.0, 1.1, 1.2]);

        // InMemoryLoader returns all documents regardless of source
        $loader = new InMemoryLoader([$document1, $document2]);
        // Need 4 vectors total: 2 for each source in the array (2 sources * 2 docs = 4)
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2, $vector3, $vector4)), 'test-embedding-model');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore());

        // With array sources, loadSource is called for each source
        // Since InMemoryLoader ignores source, it returns all docs each time
        // So with 2 sources and 2 docs each time = 4 documents total
        $indexer->index(['source1', 'source2']);

        $this->assertCount(4, $store->documents);
    }

    public function testIndexWithTextContainsFilter()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Regular blog post'),
            new TextDocument(Uuid::v4(), 'Week of Symfony news roundup'),
            new TextDocument(Uuid::v4(), 'Another regular post'),
        ];
        // Filter will remove the "Week of Symfony" document, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');
        $filter = new TextContainsFilter('Week of Symfony');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), [$filter]);
        $indexer->index();

        // Should only have 2 documents (the "Week of Symfony" one should be filtered out)
        $this->assertCount(2, $store->documents);
    }

    public function testIndexWithMultipleFilters()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Regular blog post'),
            new TextDocument(Uuid::v4(), 'Week of Symfony news'),
            new TextDocument(Uuid::v4(), 'SPAM content here'),
            new TextDocument(Uuid::v4(), 'Good content'),
        ];
        // Filters will remove "Week of Symfony" and "SPAM" documents, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');
        $filters = [
            new TextContainsFilter('Week of Symfony'),
            new TextContainsFilter('SPAM'),
        ];

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), $filters);
        $indexer->index();

        // Should only have 2 documents (filtered out "Week of Symfony" and "SPAM")
        $this->assertCount(2, $store->documents);
    }

    public function testIndexWithFiltersAndTransformers()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Regular blog post'),
            new TextDocument(Uuid::v4(), 'Week of Symfony news'),
            new TextDocument(Uuid::v4(), 'Good content'),
        ];
        // Filter will remove "Week of Symfony" document, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');
        $filter = new TextContainsFilter('Week of Symfony');
        $transformer = new class implements TransformerInterface {
            public function transform(iterable $documents, array $options = []): iterable
            {
                foreach ($documents as $document) {
                    $metadata = new Metadata($document->getMetadata()->getArrayCopy());
                    $metadata['transformed'] = true;
                    $metadata['original_content'] = $document->getContent();
                    yield new TextDocument($document->getId(), strtoupper($document->getContent()), $metadata);
                }
            }
        };

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), [$filter], [$transformer]);
        $indexer->index();

        // Should have 2 documents (filtered out "Week of Symfony"), and transformation should have occurred
        $this->assertCount(2, $store->documents);
        $this->assertTrue($store->documents[0]->metadata['transformed']);
        $this->assertTrue($store->documents[1]->metadata['transformed']);
        $this->assertSame('Regular blog post', $store->documents[0]->metadata['original_content']);
        $this->assertSame('Good content', $store->documents[1]->metadata['original_content']);
    }

    public function testIndexWithFiltersAndTransformersAppliesBoth()
    {
        $documents = [
            new TextDocument(Uuid::v4(), 'Keep this document'),
            new TextDocument(Uuid::v4(), 'Remove this content'),  // Will be filtered out
            new TextDocument(Uuid::v4(), 'Also keep this one'),
        ];
        // Filter will remove the "Remove" document, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');

        $filter = new class implements FilterInterface {
            public function filter(iterable $documents, array $options = []): iterable
            {
                foreach ($documents as $document) {
                    if (!str_contains($document->getContent(), 'Remove')) {
                        yield $document;
                    }
                }
            }
        };

        $transformer = new class implements TransformerInterface {
            public function transform(iterable $documents, array $options = []): iterable
            {
                foreach ($documents as $document) {
                    $metadata = new Metadata($document->getMetadata()->getArrayCopy());
                    $metadata['transformed'] = true;
                    yield new TextDocument($document->getId(), $document->getContent(), $metadata);
                }
            }
        };

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), [$filter], [$transformer]);
        $indexer->index();

        // Should have 2 documents (one filtered out)
        $this->assertCount(2, $store->documents);

        // Both remaining documents should be transformed
        foreach ($store->documents as $document) {
            $this->assertTrue($document->metadata['transformed']);
        }
    }

    public function testIndexWithNoFilters()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), []);
        $indexer->index();

        $this->assertCount(1, $store->documents);
    }

    public function testIndexWithSourceAndFilters()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');
        $filter = new TextContainsFilter('nonexistent');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), [$filter]);
        $indexer->index('source1');

        // Filter should still work when source is provided
        $this->assertCount(1, $store->documents);
    }
}
