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
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Indexer::class)]
#[Medium]
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
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), Embeddings::create());

        $indexer = new Indexer($vectorizer, $store = new TestStore());
        $indexer->index($document);

        $this->assertCount(1, $store->documents);
        $this->assertInstanceOf(VectorDocument::class, $store->documents[0]);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
    }

    public function testIndexEmptyDocumentList()
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug')->with('No documents to index');
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(), Embeddings::create());

        $indexer = new Indexer($vectorizer, $store = new TestStore(), $logger);
        $indexer->index([]);

        $this->assertSame([], $store->documents);
    }

    public function testIndexDocumentWithMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $document = new TextDocument($id = Uuid::v4(), 'Test content', $metadata);
        $vector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(PlatformTestHandler::createPlatform(new VectorResult($vector)), Embeddings::create());

        $indexer = new Indexer($vectorizer, $store = new TestStore());
        $indexer->index($document);

        $this->assertSame(1, $store->addCalls);
        $this->assertCount(1, $store->documents);
        $this->assertInstanceOf(VectorDocument::class, $store->documents[0]);
        $this->assertSame($id, $store->documents[0]->id);
        $this->assertSame($vector, $store->documents[0]->vector);
        $this->assertSame(['key' => 'value'], $store->documents[0]->metadata->getArrayCopy());
    }
}
