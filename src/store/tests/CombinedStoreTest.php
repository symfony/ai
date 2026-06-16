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
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\CombinedStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

final class CombinedStoreTest extends TestCase
{
    public function testRrfScoringWithKnownRanks()
    {
        $docA = new VectorDocument('doc-a', new Vector([0.1, 0.2]), new Metadata([Metadata::KEY_TEXT => 'Document A']));
        $docB = new VectorDocument('doc-b', new Vector([0.3, 0.4]), new Metadata([Metadata::KEY_TEXT => 'Document B']));
        $docC = new VectorDocument('doc-c', new Vector([0.5, 0.6]), new Metadata([Metadata::KEY_TEXT => 'Document C']));

        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturnMap([
            [VectorQuery::class, true],
        ]);
        $vectorStore->method('query')->willReturn([$docA, $docB]);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('supports')->willReturnMap([
            [TextQuery::class, true],
        ]);
        $textStore->method('query')->willReturn([$docB, $docC]);

        $store = new CombinedStore($vectorStore, $textStore, 60);

        $query = new HybridQuery(new Vector([0.1, 0.2]), 'test query');
        $results = iterator_to_array($store->query($query));

        // docB appears in both lists (rank 2 in vector, rank 1 in text) so should score highest
        $this->assertCount(3, $results);
        $this->assertSame('doc-b', $results[0]->getId());
    }

    public function testRrfScoresAreUpdatedOnDocuments()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturnMap([
            [VectorQuery::class, true],
        ]);
        $vectorStore->method('query')->willReturn([$doc]);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('supports')->willReturnMap([
            [TextQuery::class, true],
        ]);
        $textStore->method('query')->willReturn([$doc]);

        $store = new CombinedStore($vectorStore, $textStore, 60);

        $query = new HybridQuery(new Vector([0.1, 0.2]), 'test query');
        $results = iterator_to_array($store->query($query));

        $this->assertCount(1, $results);
        // Score should be sum of RRF contributions from both lists: 2 * (1 / (60 + 0 + 1))
        $expectedScore = 2.0 / 61.0;
        $this->assertEqualsWithDelta($expectedScore, $results[0]->getScore(), 0.0001);
    }

    public function testHybridQueryDecomposesIntoVectorAndTextQueries()
    {
        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturnMap([
            [VectorQuery::class, true],
        ]);
        $vectorStore->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(VectorQuery::class), $this->anything())
            ->willReturn([]);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('supports')->willReturnMap([
            [TextQuery::class, true],
        ]);
        $textStore->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(TextQuery::class), $this->anything())
            ->willReturn([]);

        $store = new CombinedStore($vectorStore, $textStore);

        $query = new HybridQuery(new Vector([0.1, 0.2]), 'test query');
        iterator_to_array($store->query($query));
    }

    public function testNonHybridVectorQueryForwardsToVectorStore()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturnMap([
            [VectorQuery::class, true],
        ]);
        $vectorStore->expects($this->once())
            ->method('query')
            ->willReturn([$doc]);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->expects($this->never())->method('query');

        $store = new CombinedStore($vectorStore, $textStore);

        $query = new VectorQuery(new Vector([0.1, 0.2]));
        $results = iterator_to_array($store->query($query));

        $this->assertCount(1, $results);
        $this->assertSame('doc-1', $results[0]->getId());
    }

    public function testNonHybridTextQueryForwardsToTextStore()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturnMap([
            [VectorQuery::class, true],
        ]);
        $vectorStore->expects($this->never())->method('query');

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('supports')->willReturnMap([
            [TextQuery::class, true],
        ]);
        $textStore->expects($this->once())
            ->method('query')
            ->willReturn([$doc]);

        $store = new CombinedStore($vectorStore, $textStore);

        $query = new TextQuery('test query');
        $results = iterator_to_array($store->query($query));

        $this->assertCount(1, $results);
    }

    public function testUnsupportedQueryThrowsException()
    {
        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturn(false);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('supports')->willReturn(false);

        $store = new CombinedStore($vectorStore, $textStore);

        $this->expectException(UnsupportedQueryTypeException::class);

        $store->query(new VectorQuery(new Vector([0.1, 0.2])));
    }

    public function testSupportsHybridQueryWhenBothSubStoresSupport()
    {
        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturnMap([
            [VectorQuery::class, true],
            [TextQuery::class, false],
            [HybridQuery::class, false],
        ]);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('supports')->willReturnMap([
            [VectorQuery::class, false],
            [TextQuery::class, true],
            [HybridQuery::class, false],
        ]);

        $store = new CombinedStore($vectorStore, $textStore);

        $this->assertTrue($store->supports(HybridQuery::class));
        $this->assertTrue($store->supports(VectorQuery::class));
        $this->assertTrue($store->supports(TextQuery::class));
    }

    public function testDoesNotSupportHybridQueryWhenVectorStoreDoesNotSupportVectorQuery()
    {
        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('supports')->willReturn(false);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('supports')->willReturnMap([
            [TextQuery::class, true],
        ]);

        $store = new CombinedStore($vectorStore, $textStore);

        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testAddDelegatesToBothStoresWhenDifferentInstances()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->expects($this->once())->method('add')->with($doc);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->expects($this->once())->method('add')->with($doc);

        $store = new CombinedStore($vectorStore, $textStore);
        $store->add($doc);
    }

    public function testAddDelegatesToStoreOnceWhenSameInstance()
    {
        $doc = new VectorDocument('doc-1', new Vector([0.1, 0.2]));

        $sharedStore = $this->createMock(StoreInterface::class);
        $sharedStore->expects($this->once())->method('add')->with($doc);

        $store = new CombinedStore($sharedStore, $sharedStore);
        $store->add($doc);
    }

    public function testRemoveDelegatesToBothStoresWhenDifferentInstances()
    {
        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->expects($this->once())->method('remove')->with('doc-1');

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->expects($this->once())->method('remove')->with('doc-1');

        $store = new CombinedStore($vectorStore, $textStore);
        $store->remove('doc-1');
    }

    public function testRemoveDelegatesToStoreOnceWhenSameInstance()
    {
        $sharedStore = $this->createMock(StoreInterface::class);
        $sharedStore->expects($this->once())->method('remove')->with('doc-1');

        $store = new CombinedStore($sharedStore, $sharedStore);
        $store->remove('doc-1');
    }

    public function testCountReturnsSumOfBothStoresWhenDifferentInstances()
    {
        $vectorStore = $this->createMock(StoreInterface::class);
        $vectorStore->method('count')->willReturn(3);

        $textStore = $this->createMock(StoreInterface::class);
        $textStore->method('count')->willReturn(5);

        $store = new CombinedStore($vectorStore, $textStore);

        $this->assertSame(8, $store->count());
    }

    public function testCountDelegatesToStoreOnceWhenSameInstance()
    {
        $sharedStore = $this->createMock(StoreInterface::class);
        $sharedStore->expects($this->once())->method('count')->willReturn(4);

        $store = new CombinedStore($sharedStore, $sharedStore);

        $this->assertSame(4, $store->count());
    }
}
