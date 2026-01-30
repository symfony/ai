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
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;

final class SourceIndexerTest extends TestCase
{
    public function testIndexSingleDocument()
    {
        $document = new TextDocument($id = Uuid::v4(), 'Test content');
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new SourceIndexer($loader, $processor);
        $indexer->index('source');

        $this->assertCount(1, $store->documents);
        $this->assertInstanceOf(VectorDocument::class, $store->documents[0]);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
    }

    public function testIndexEmptyDocumentList()
    {
        $loader = new InMemoryLoader([]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new SourceIndexer($loader, $processor);
        $indexer->index('source');

        $this->assertSame([], $store->documents);
    }

    public function testIndexDocumentWithMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $document = new TextDocument($id = Uuid::v4(), 'Test content', $metadata);
        $vector = new Vector([0.1, 0.2, 0.3]);
        $loader = new InMemoryLoader([$document]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new SourceIndexer($loader, $processor);
        $indexer->index('source');

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

        $loader = new InMemoryLoader([$document1]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new SourceIndexer($loader, $processor);
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

        $processor = new DocumentProcessor($vectorizer, $store = new TestStore());
        $indexer = new SourceIndexer($loader, $processor);

        // With array sources, loadSource is called for each source
        // Since InMemoryLoader ignores source, it returns all docs each time
        // So with 2 sources and 2 docs each time = 4 documents total
        $indexer->index(['source1', 'source2']);

        $this->assertCount(4, $store->documents);
    }

    public function testIndexThrowsExceptionForObjectInput()
    {
        $loader = new InMemoryLoader([]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, new TestStore());
        $indexer = new SourceIndexer($loader, $processor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SourceIndexer expects a string or iterable of strings');

        $indexer->index(new TextDocument(Uuid::v4(), 'Test content')); /* @phpstan-ignore argument.type */
    }

    public function testIndexThrowsExceptionForNonStringInIterable()
    {
        $loader = new InMemoryLoader([]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), 'text-embedding-3-small');

        $processor = new DocumentProcessor($vectorizer, new TestStore());
        $indexer = new SourceIndexer($loader, $processor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SourceIndexer expects sources to be strings');

        $indexer->index([new TextDocument(Uuid::v4(), 'Test content')]); /* @phpstan-ignore argument.type */
    }
}
