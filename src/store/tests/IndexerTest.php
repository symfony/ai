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

    public function testWithSource()
    {
        $document1 = new TextDocument(Uuid::v4(), 'Document 1');
        $vector = new Vector([0.1, 0.2, 0.3]);

        // InMemoryLoader doesn't use source parameter, so we'll test withSource method's immutability
        $loader = new InMemoryLoader([$document1]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), 'source1');

        $indexerWithNewSource = $indexer->withSource('source2');

        $this->assertNotSame($indexer, $indexerWithNewSource);

        // Both can index successfully
        $indexer->index();
        $this->assertCount(1, $store->documents);

        $store2 = new TestStore();
        $indexer2 = new Indexer($loader, $vectorizer, $store2, 'source2');
        $indexer2->index();
        $this->assertCount(1, $store2->documents);
    }

    public function testWithSourceArray()
    {
        $document1 = new TextDocument(Uuid::v4(), 'Document 1');
        $document2 = new TextDocument(Uuid::v4(), 'Document 2');
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $vector3 = new Vector([0.7, 0.8, 0.9]);
        $vector4 = new Vector([1.0, 1.1, 1.2]);
        $vector5 = new Vector([1.3, 1.4, 1.5]);
        $vector6 = new Vector([1.6, 1.7, 1.8]);

        // InMemoryLoader returns all documents regardless of source
        $loader = new InMemoryLoader([$document1, $document2]);
        // Need 6 vectors total: 2 for first indexer, then 2 for each source in the second indexer (2 sources * 2 docs = 4)
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2, $vector3, $vector4, $vector5, $vector6)), 'test-embedding-model');

        // Create indexer with single source
        $indexer = new Indexer($loader, $vectorizer, $store1 = new TestStore(), 'source1');

        $indexerWithMultipleSources = $indexer->withSource(['source2', 'source3']);

        $this->assertNotSame($indexer, $indexerWithMultipleSources);

        // Since InMemoryLoader ignores source, both will index all documents
        $indexer->index();
        $this->assertCount(2, $store1->documents);

        $store2 = new TestStore();
        $indexer2 = new Indexer($loader, $vectorizer, $store2, ['source2', 'source3']);
        $indexer2->index();
        // With array sources, loadSource is called for each source
        // Since InMemoryLoader ignores source, it returns all docs each time
        // So with 2 sources and 2 docs each time = 4 documents total
        $this->assertCount(4, $store2->documents);
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

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), null, [$filter]);
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

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), null, $filters);
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
                    $metadata = new Metadata($document->metadata->getArrayCopy());
                    $metadata['transformed'] = true;
                    $metadata['original_content'] = $document->content;
                    yield new TextDocument($document->id, strtoupper($document->content), $metadata);
                }
            }
        };

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), null, [$filter], [$transformer]);
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
                    if (!str_contains($document->content, 'Remove')) {
                        yield $document;
                    }
                }
            }
        };

        $transformer = new class implements TransformerInterface {
            public function transform(iterable $documents, array $options = []): iterable
            {
                foreach ($documents as $document) {
                    $metadata = new Metadata($document->metadata->getArrayCopy());
                    $metadata['transformed'] = true;
                    yield new TextDocument($document->id, $document->content, $metadata);
                }
            }
        };

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), null, [$filter], [$transformer]);
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

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), null, []);
        $indexer->index();

        $this->assertCount(1, $store->documents);
    }

    public function testWithSourcePreservesFilters()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');
        $filter = new TextContainsFilter('nonexistent');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), 'source1', [$filter]);
        $indexerWithNewSource = $indexer->withSource('source2');

        $this->assertNotSame($indexer, $indexerWithNewSource);

        $indexerWithNewSource->index();
        $this->assertCount(1, $store->documents); // Filter should still work
    }
}
