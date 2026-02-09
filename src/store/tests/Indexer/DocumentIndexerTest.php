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
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;

final class DocumentIndexerTest extends TestCase
{
    public function testIndexSingleDocument()
    {
        $document = new TextDocument($id = Uuid::v4()->toString(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new DocumentIndexer($processor);
        $indexer->index($document);

        $this->assertCount(1, $store->documents);
        $this->assertInstanceOf(VectorDocument::class, $store->documents[0]);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
    }

    public function testIndexEmptyDocumentList()
    {
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new DocumentIndexer($processor);
        $indexer->index([]);

        $this->assertSame([], $store->documents);
    }

    public function testIndexDocumentWithMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $document = new TextDocument($id = Uuid::v4()->toString(), 'Test content', $metadata);
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new DocumentIndexer($processor);
        $indexer->index($document);

        $this->assertSame(1, $store->addCalls);
        $this->assertCount(1, $store->documents);
        $this->assertInstanceOf(VectorDocument::class, $store->documents[0]);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
        $this->assertSame(['key' => 'value'], $store->documents[0]->metadata->getArrayCopy());
    }

    public function testIndexMultipleDocuments()
    {
        $document1 = new TextDocument(Uuid::v4()->toString(), 'Document 1');
        $document2 = new TextDocument(Uuid::v4()->toString(), 'Document 2');
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);

        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new DocumentIndexer($processor);
        $indexer->index([$document1, $document2]);

        $this->assertCount(2, $store->documents);
    }

    public function testIndexWithGenerator()
    {
        $vector1 = new Vector([0.1, 0.2, 0.3]);
        $vector2 = new Vector([0.4, 0.5, 0.6]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector1, $vector2)), 'test-embedding-model');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new DocumentIndexer($processor);

        $generator = (static function () {
            yield new TextDocument(Uuid::v4(), 'Document 1');
            yield new TextDocument(Uuid::v4(), 'Document 2');
        })();

        $indexer->index($generator);

        $this->assertCount(2, $store->documents);
    }

    public function testIndexThrowsExceptionForStringInput()
    {
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, new TestStore());
        $indexer = new DocumentIndexer($processor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocumentIndexer expects an EmbeddableDocumentInterface or iterable, got "string".');

        $indexer->index('source-path');
    }

    public function testIndexThrowsExceptionForNonIterableNonDocumentObject()
    {
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, new TestStore());
        $indexer = new DocumentIndexer($processor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocumentIndexer expects an EmbeddableDocumentInterface or iterable');

        $indexer->index(new \stdClass()); /* @phpstan-ignore argument.type */
    }
}
