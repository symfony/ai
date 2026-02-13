<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Indexer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Filter\TextContainsFilter;
use Symfony\AI\Store\Document\FilterInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;

final class DocumentProcessorTest extends TestCase
{
    public function testProcessSingleDocument()
    {
        $document = new TextDocument($id = Uuid::v4()->toString(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $processor->process([$document]);

        $this->assertCount(1, $store->documents);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
    }

    public function testProcessEmptyDocumentList()
    {
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $processor->process([]);

        $this->assertSame([], $store->documents);
    }

    public function testProcessDocumentWithMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $document = new TextDocument($id = Uuid::v4()->toString(), 'Test content', $metadata);
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $processor->process([$document]);

        $this->assertSame(1, $store->addCalls);
        $this->assertCount(1, $store->documents);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
        $this->assertSame(['key' => 'value'], $store->documents[0]->metadata->getArrayCopy());
    }

    public function testProcessMultipleDocuments()
    {
        $document1 = new TextDocument(Uuid::v4()->toString(), 'Document 1');
        $document2 = new TextDocument(Uuid::v4()->toString(), 'Document 2');
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);

        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $processor->process([$document1, $document2]);

        $this->assertCount(2, $store->documents);
    }

    public function testProcessWithTextContainsFilter()
    {
        $documents = [
            new TextDocument(Uuid::v4()->toString(), 'Regular blog post'),
            new TextDocument(Uuid::v4()->toString(), 'Week of Symfony news roundup'),
            new TextDocument(Uuid::v4()->toString(), 'Another regular post'),
        ];
        // Filter will remove the "Week of Symfony" document, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');
        $filter = new TextContainsFilter('Week of Symfony');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore(), [$filter]);
        $processor->process($documents);

        // Should only have 2 documents (the "Week of Symfony" one should be filtered out)
        $this->assertCount(2, $store->documents);
    }

    public function testProcessWithMultipleFilters()
    {
        $documents = [
            new TextDocument(Uuid::v4()->toString(), 'Regular blog post'),
            new TextDocument(Uuid::v4()->toString(), 'Week of Symfony news'),
            new TextDocument(Uuid::v4()->toString(), 'SPAM content here'),
            new TextDocument(Uuid::v4()->toString(), 'Good content'),
        ];
        // Filters will remove "Week of Symfony" and "SPAM" documents, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');
        $filters = [
            new TextContainsFilter('Week of Symfony'),
            new TextContainsFilter('SPAM'),
        ];

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore(), $filters);
        $processor->process($documents);

        // Should only have 2 documents (filtered out "Week of Symfony" and "SPAM")
        $this->assertCount(2, $store->documents);
    }

    public function testProcessWithFiltersAndTransformers()
    {
        $documents = [
            new TextDocument(Uuid::v4()->toString(), 'Regular blog post'),
            new TextDocument(Uuid::v4()->toString(), 'Week of Symfony news'),
            new TextDocument(Uuid::v4()->toString(), 'Good content'),
        ];
        // Filter will remove "Week of Symfony" document, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
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

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore(), [$filter], [$transformer]);
        $processor->process($documents);

        // Should have 2 documents (filtered out "Week of Symfony"), and transformation should have occurred
        $this->assertCount(2, $store->documents);
        $this->assertTrue($store->documents[0]->metadata['transformed']);
        $this->assertTrue($store->documents[1]->metadata['transformed']);
        $this->assertSame('Regular blog post', $store->documents[0]->metadata['original_content']);
        $this->assertSame('Good content', $store->documents[1]->metadata['original_content']);
    }

    public function testProcessWithFiltersAndTransformersAppliesBoth()
    {
        $documents = [
            new TextDocument(Uuid::v4()->toString(), 'Keep this document'),
            new TextDocument(Uuid::v4()->toString(), 'Remove this content'),  // Will be filtered out
            new TextDocument(Uuid::v4()->toString(), 'Also keep this one'),
        ];
        // Filter will remove the "Remove" document, leaving 2 documents
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
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

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore(), [$filter], [$transformer]);
        $processor->process($documents);

        // Should have 2 documents (one filtered out)
        $this->assertCount(2, $store->documents);

        // Both remaining documents should be transformed
        foreach ($store->documents as $document) {
            $this->assertTrue($document->metadata['transformed']);
        }
    }

    public function testProcessWithNoFilters()
    {
        $document = new TextDocument(Uuid::v4()->toString(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore(), []);
        $processor->process([$document]);

        $this->assertCount(1, $store->documents);
    }

    public function testProcessThrowsExceptionForNonDocumentInIterable()
    {
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, new TestStore());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocumentProcessor expects documents to be instances of EmbeddableDocumentInterface, got "string".');

        $processor->process(['not-a-document']); /* @phpstan-ignore argument.type */
    }

    public function testProcessWithChunking()
    {
        $documents = [];
        $vectors = [];
        for ($i = 0; $i < 100; ++$i) {
            $documents[] = new TextDocument(Uuid::v4()->toString(), 'Document '.$i);
            $vectors[] = new Vector([0.1 * $i, 0.2 * $i, 0.3 * $i]);
        }

        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult(...$vectors)), 'test-embedding-model');
        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());

        // With default chunk_size of 50 and 100 documents, there should be 2 add calls
        $processor->process($documents);

        $this->assertCount(100, $store->documents);
        $this->assertSame(2, $store->addCalls);
    }

    public function testProcessWithCustomChunkSize()
    {
        $documents = [];
        $vectors = [];
        for ($i = 0; $i < 100; ++$i) {
            $documents[] = new TextDocument(Uuid::v4()->toString(), 'Document '.$i);
            $vectors[] = new Vector([0.1 * $i, 0.2 * $i, 0.3 * $i]);
        }

        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult(...$vectors)), 'test-embedding-model');
        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());

        // With chunk_size of 10 and 100 documents, there should be 10 add calls
        $processor->process($documents, ['chunk_size' => 10]);

        $this->assertCount(100, $store->documents);
        $this->assertSame(10, $store->addCalls);
    }
}
