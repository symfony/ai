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
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Event\PostQueryEvent;
use Symfony\AI\Store\Event\PreQueryEvent;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\Retriever;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RetrieverTest extends TestCase
{
    public function testRetrieveReturnsDocuments()
    {
        $document1 = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Document 1']),
        );
        $document2 = new VectorDocument(
            Uuid::v4(),
            new Vector([0.4, 0.5, 0.6]),
            new Metadata(['title' => 'Document 2']),
        );

        $store = new TestStore();
        $store->add([$document1, $document2]);

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(2, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertInstanceOf(VectorDocument::class, $results[1]);
        $this->assertSame('Document 1', $results[0]->getMetadata()['title']);
        $this->assertSame('Document 2', $results[1]->getMetadata()['title']);
    }

    public function testRetrieveWithEmptyStore()
    {
        $store = new TestStore();

        $queryVector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(0, $results);
    }

    public function testRetrievePassesOptionsToStore()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $store = new TestStore();
        $store->add($document);

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query', ['maxItems' => 10]));

        $this->assertCount(1, $results);
    }

    public function testRetrieveUsesTextQueryWhenNoVectorizerProvided()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, true],
                [HybridQuery::class, false],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(static function ($query) {
                    return $query instanceof TextQuery && ['test', 'query'] === $query->getTexts();
                }),
                $this->anything()
            )
            ->willReturn([$document]);

        $retriever = new Retriever($store, null);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(1, $results);
    }

    public function testRetrieveUsesTextQueryWhenStoreDoesNotSupportVectorQuery()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, false],
                [TextQuery::class, true],
                [HybridQuery::class, false],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(static function ($query) {
                    return $query instanceof TextQuery && ['test', 'query'] === $query->getTexts();
                }),
                $this->anything()
            )
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(1, $results);
    }

    public function testRetrieveUsesHybridQueryWhenStoreSupportsIt()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, true],
                [HybridQuery::class, true],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(static function ($query) {
                    return $query instanceof HybridQuery && ['test', 'query'] === $query->getTexts();
                }),
                $this->anything()
            )
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(1, $results);
    }

    public function testRetrieveUsesVectorQueryWhenStoreOnlySupportsVectorQuery()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, false],
                [HybridQuery::class, false],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with(
                $this->isInstanceOf(VectorQuery::class),
                $this->anything()
            )
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(1, $results);
    }

    public function testRetrievePassesSemanticRatioToHybridQuery()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, true],
                [HybridQuery::class, true],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(static function ($query) {
                    return $query instanceof HybridQuery && 0.7 === $query->getSemanticRatio();
                }),
                $this->anything()
            )
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query', ['semanticRatio' => 0.7]));

        $this->assertCount(1, $results);
    }

    public function testRetrievePassesPlatformOptionsToVectorizer()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->expects($this->once())
            ->method('vectorize')
            ->with('test query', ['input_type' => 'search_query'])
            ->willReturn(new Vector([0.2, 0.3, 0.4]));

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, false],
                [HybridQuery::class, false],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(VectorQuery::class), ['maxItems' => 5])
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query', [
            'maxItems' => 5,
            'platform_options' => ['input_type' => 'search_query'],
        ]));

        $this->assertCount(1, $results);
    }

    public function testRetrievePassesPlatformOptionsToVectorizerForHybridQuery()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->expects($this->once())
            ->method('vectorize')
            ->with('test query', ['input_type' => 'search_query'])
            ->willReturn(new Vector([0.2, 0.3, 0.4]));

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, true],
                [HybridQuery::class, true],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(HybridQuery::class), ['semanticRatio' => 0.5])
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query', [
            'semanticRatio' => 0.5,
            'platform_options' => ['input_type' => 'search_query'],
        ]));

        $this->assertCount(1, $results);
    }

    public function testRetrieveDoesNotPassStoreOptionsToVectorizer()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->expects($this->once())
            ->method('vectorize')
            ->with('test query', [])
            ->willReturn(new Vector([0.2, 0.3, 0.4]));

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, false],
                [HybridQuery::class, false],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query', ['maxItems' => 5]));

        $this->assertCount(1, $results);
    }

    public function testRetrieveWithPreQueryEventCanSetPlatformOptions()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->expects($this->once())
            ->method('vectorize')
            ->with('test query', ['input_type' => 'search_query'])
            ->willReturn(new Vector([0.2, 0.3, 0.4]));

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, false],
                [HybridQuery::class, false],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->willReturn([$document]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event) {
                if ($event instanceof PreQueryEvent) {
                    $event->setOptions(['platform_options' => ['input_type' => 'search_query']]);
                }

                return $event;
            });

        $retriever = new Retriever($store, $vectorizer, $eventDispatcher);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(1, $results);
    }

    public function testRetrieveDoesNotPassPlatformOptionsToStore()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $vectorizer = $this->createMock(VectorizerInterface::class);
        $vectorizer->method('vectorize')
            ->willReturn(new Vector([0.2, 0.3, 0.4]));

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, false],
                [HybridQuery::class, false],
            ]);

        // Stores merging their options into the query payload (e.g. the MongoDB bridge)
        // would send `platform_options` to the vector database.
        $store->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(VectorQuery::class), [])
            ->willReturn([$document]);

        $retriever = new Retriever($store, $vectorizer);
        $results = iterator_to_array($retriever->retrieve('test query', [
            'platform_options' => ['input_type' => 'search_query'],
        ]));

        $this->assertCount(1, $results);
    }

    public function testRetrieveWithEventDispatcherDispatchesPostQueryEvent()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $store = new TestStore();
        $store->add([$document]);

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $postRetrievalEvent = null;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$postRetrievalEvent) {
                if ($event instanceof PostQueryEvent) {
                    $postRetrievalEvent = $event;
                }

                return $event;
            });

        $retriever = new Retriever($store, $vectorizer, $dispatcher);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(PostQueryEvent::class, $postRetrievalEvent);
        $this->assertSame('test query', $postRetrievalEvent->getQuery());
    }

    public function testRetrieveWithEventDispatcherReturnsModifiedDocuments()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Original']),
        );
        $rerankedDocument = new VectorDocument(
            Uuid::v4(),
            new Vector([0.4, 0.5, 0.6]),
            new Metadata(['title' => 'Reranked']),
        );

        $store = new TestStore();
        $store->add([$document]);

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use ($rerankedDocument) {
                if ($event instanceof PostQueryEvent) {
                    $event->setDocuments([$rerankedDocument]);
                }

                return $event;
            });

        $retriever = new Retriever($store, $vectorizer, $dispatcher);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(1, $results);
        $this->assertSame('Reranked', $results[0]->getMetadata()['title']);
    }

    public function testRetrieveWithoutEventDispatcherYieldsDocuments()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $store = new TestStore();
        $store->add([$document]);

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $retriever = new Retriever($store, $vectorizer);
        $results = $retriever->retrieve('test query');

        // Without dispatcher, should be a Generator (yield behavior)
        $this->assertInstanceOf(\Generator::class, $results);
        $this->assertCount(1, iterator_to_array($results));
    }

    public function testRetrieveWithPreQueryEventModifiesQuery()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, true],
                [HybridQuery::class, true],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(static function ($query) {
                    return $query instanceof HybridQuery && ['expanded', 'query', 'terms'] === $query->getTexts();
                }),
                $this->anything()
            )
            ->willReturn([$document]);

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) {
                if ($event instanceof PreQueryEvent) {
                    $event->setQuery('expanded query terms');
                }

                return $event;
            });

        $retriever = new Retriever($store, $vectorizer, $dispatcher);
        $results = iterator_to_array($retriever->retrieve('original query'));

        $this->assertCount(1, $results);
    }

    public function testRetrieveWithPreQueryEventModifiesOptions()
    {
        $document = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['title' => 'Test Document']),
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('supports')
            ->willReturnMap([
                [VectorQuery::class, true],
                [TextQuery::class, true],
                [HybridQuery::class, true],
            ]);

        $store->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(static function ($query) {
                    return $query instanceof HybridQuery && 0.9 === $query->getSemanticRatio();
                }),
                $this->anything()
            )
            ->willReturn([$document]);

        $queryVector = new Vector([0.2, 0.3, 0.4]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult([$queryVector])),
            'text-embedding-3-small'
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) {
                if ($event instanceof PreQueryEvent) {
                    $event->setOptions(['semanticRatio' => 0.9]);
                }

                return $event;
            });

        $retriever = new Retriever($store, $vectorizer, $dispatcher);
        $results = iterator_to_array($retriever->retrieve('test query', ['semanticRatio' => 0.5]));

        $this->assertCount(1, $results);
    }
}
