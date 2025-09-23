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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Result\ToolCall;
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

#[CoversClass(Indexer::class)]
#[Medium]
#[UsesClass(InMemoryLoader::class)]
#[UsesClass(TextDocument::class)]
#[UsesClass(Vector::class)]
#[UsesClass(VectorDocument::class)]
#[UsesClass(ToolCallMessage::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(Embeddings::class)]
#[UsesClass(Platform::class)]
#[UsesClass(ResultPromise::class)]
#[UsesClass(VectorResult::class)]
final class IndexerTest extends TestCase
{
    public function testIndexSingleDocument()
    {
        $document = new TextDocument($id = Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));

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
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), new Embeddings(Embeddings::TEXT_3_SMALL));

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
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));

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
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));

        // Create indexer with initial source
        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), 'source1');

        // Create new indexer with different source
        $indexerWithNewSource = $indexer->withSource('source2');

        // Verify it returns a new instance (immutability)
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
        $vector = new Vector([0.1, 0.2, 0.3]);

        // InMemoryLoader returns all documents regardless of source
        $loader = new InMemoryLoader([$document1, $document2]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));

        // Create indexer with single source
        $indexer = new Indexer($loader, $vectorizer, $store1 = new TestStore(), 'source1');

        // Create new indexer with array of sources
        $indexerWithMultipleSources = $indexer->withSource(['source2', 'source3']);

        // Verify it returns a new instance (immutability)
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
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));
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
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));
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
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));
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
        // Test that both filters and transformers are applied correctly
        $documents = [
            new TextDocument(Uuid::v4(), 'Keep this document'),
            new TextDocument(Uuid::v4(), 'Remove this content'),  // Will be filtered out
            new TextDocument(Uuid::v4(), 'Also keep this one'),
        ];
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader($documents);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));

        // Filter that removes documents containing "Remove"
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

        // Transformer that adds metadata
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
        // Test that indexer works with empty filters array (backward compatibility)
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), null, []);
        $indexer->index();

        $this->assertCount(1, $store->documents);
    }

    public function testWithSourcePreservesFilters()
    {
        $document = new TextDocument(Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), new Embeddings(Embeddings::TEXT_3_SMALL));
        $filter = new TextContainsFilter('nonexistent');

        $indexer = new Indexer($loader, $vectorizer, $store = new TestStore(), 'source1', [$filter]);
        $indexerWithNewSource = $indexer->withSource('source2');

        // Verify that the new indexer preserves filters
        $this->assertNotSame($indexer, $indexerWithNewSource);

        $indexerWithNewSource->index();
        $this->assertCount(1, $store->documents); // Filter should still work
    }
}
