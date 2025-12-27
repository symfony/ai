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
use Symfony\AI\Store\Retriever;
use Symfony\AI\Store\Tests\Double\PlatformTestHandler;
use Symfony\AI\Store\Tests\Double\TestStore;
use Symfony\Component\Uid\Uuid;

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
            PlatformTestHandler::createPlatform(new VectorResult($queryVector)),
            'text-embedding-3-small'
        );

        $retriever = new Retriever($vectorizer, $store);
        $results = iterator_to_array($retriever->retrieve('test query'));

        $this->assertCount(2, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertInstanceOf(VectorDocument::class, $results[1]);
        $this->assertSame('Document 1', $results[0]->metadata['title']);
        $this->assertSame('Document 2', $results[1]->metadata['title']);
    }

    public function testRetrieveWithEmptyStore()
    {
        $store = new TestStore();

        $queryVector = new Vector([0.1, 0.2, 0.3]);
        $vectorizer = new Vectorizer(
            PlatformTestHandler::createPlatform(new VectorResult($queryVector)),
            'text-embedding-3-small'
        );

        $retriever = new Retriever($vectorizer, $store);
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
            PlatformTestHandler::createPlatform(new VectorResult($queryVector)),
            'text-embedding-3-small'
        );

        $retriever = new Retriever($vectorizer, $store);
        $results = iterator_to_array($retriever->retrieve('test query', ['maxItems' => 10]));

        $this->assertCount(1, $results);
    }
}
