<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Cache\Store;
use Symfony\AI\Store\Distance\DistanceCalculator;
use Symfony\AI\Store\Distance\DistanceStrategy;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetup()
    {
        $store = new Store(new ArrayAdapter());
        $store->setup();

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(0, $result);
    }

    public function testStoreCanDrop()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(3, $result);

        $store->drop();

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(0, $result);
    }

    public function testStoreCanSearchUsingCosineDistance()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(3, $result);
        $this->assertSame([0.1, 0.1, 0.5], $result[0]->getVector()->getData());

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(6, $result);
        $this->assertSame([0.1, 0.1, 0.5], $result[0]->getVector()->getData());
    }

    public function testStoreCanSearchUsingCosineDistanceAndReturnCorrectOrder()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.1, 0.6])),
            new VectorDocument(Uuid::v4(), new Vector([0.0, 0.1, 0.6])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(5, $result);
        $this->assertSame([0.0, 0.1, 0.6], $result[0]->getVector()->getData());
        $this->assertSame([0.1, 0.1, 0.5], $result[1]->getVector()->getData());
        $this->assertSame([0.3, 0.1, 0.6], $result[2]->getVector()->getData());
        $this->assertSame([0.3, 0.7, 0.1], $result[3]->getVector()->getData());
        $this->assertSame([0.7, -0.3, 0.0], $result[4]->getVector()->getData());
    }

    public function testStoreCanSearchUsingCosineDistanceWithMaxItems()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
        ]);

        $this->assertCount(1, iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6])), [
            'maxItems' => 1,
        ])));
    }

    public function testStoreCanSearchUsingAngularDistance()
    {
        $store = new Store(new ArrayAdapter(), new DistanceCalculator(DistanceStrategy::ANGULAR_DISTANCE));
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 5.0, 7.0])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.2, 2.3, 3.4]))));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->getVector()->getData());
    }

    public function testStoreCanSearchUsingEuclideanDistance()
    {
        $store = new Store(new ArrayAdapter(), new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE));
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([1.0, 5.0, 7.0])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.2, 2.3, 3.4]))));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->getVector()->getData());
    }

    public function testStoreCanSearchUsingManhattanDistance()
    {
        $store = new Store(new ArrayAdapter(), new DistanceCalculator(DistanceStrategy::MANHATTAN_DISTANCE));
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 5.0, 7.0])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.2, 2.3, 3.4]))));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->getVector()->getData());
    }

    public function testStoreCanSearchUsingChebyshevDistance()
    {
        $store = new Store(new ArrayAdapter(), new DistanceCalculator(DistanceStrategy::CHEBYSHEV_DISTANCE));
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 5.0, 7.0])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.2, 2.3, 3.4]))));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->getVector()->getData());
    }

    public function testStoreCanSearchWithFilter()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['category' => 'products', 'enabled' => true])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['category' => 'articles', 'enabled' => true])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['category' => 'products', 'enabled' => false])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6])), [
            'filter' => static fn (VectorDocument $doc) => 'products' === $doc->getMetadata()['category'],
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('products', $result[0]->getMetadata()['category']);
        $this->assertSame('products', $result[1]->getMetadata()['category']);
    }

    public function testStoreCanSearchWithFilterAndMaxItems()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['category' => 'products'])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['category' => 'articles'])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['category' => 'products'])),
            new VectorDocument(Uuid::v4(), new Vector([0.0, 0.1, 0.6]), new Metadata(['category' => 'products'])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6])), [
            'filter' => static fn (VectorDocument $doc) => 'products' === $doc->getMetadata()['category'],
            'maxItems' => 2,
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('products', $result[0]->getMetadata()['category']);
        $this->assertSame('products', $result[1]->getMetadata()['category']);
    }

    public function testStoreCanSearchWithComplexFilter()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['price' => 100, 'stock' => 5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['price' => 200, 'stock' => 0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['price' => 50, 'stock' => 10])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6])), [
            'filter' => static fn (VectorDocument $doc) => $doc->getMetadata()['price'] <= 150 && $doc->getMetadata()['stock'] > 0,
        ]));

        $this->assertCount(2, $result);
    }

    public function testStoreCanSearchWithNestedMetadataFilter()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['options' => ['size' => 'S', 'color' => 'blue']])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['options' => ['size' => 'M', 'color' => 'blue']])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['options' => ['size' => 'S', 'color' => 'red']])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6])), [
            'filter' => static fn (VectorDocument $doc) => 'S' === $doc->getMetadata()['options']['size'],
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('S', $result[0]->getMetadata()['options']['size']);
        $this->assertSame('S', $result[1]->getMetadata()['options']['size']);
    }

    public function testStoreCanSearchWithInArrayFilter()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['brand' => 'Nike'])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['brand' => 'Adidas'])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['brand' => 'Generic'])),
        ]);

        $allowedBrands = ['Nike', 'Adidas', 'Puma'];
        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6])), [
            'filter' => static fn (VectorDocument $doc) => \in_array($doc->getMetadata()['brand'] ?? '', $allowedBrands, true),
        ]));

        $this->assertCount(2, $result);
    }

    public function testRemoveWithStringId()
    {
        $store = new Store(new ArrayAdapter());
        $store->setup();

        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $id3 = Uuid::v4();

        $store->add([
            new VectorDocument($id1, new Vector([0.1, 0.1, 0.5])),
            new VectorDocument($id2, new Vector([0.7, -0.3, 0.0])),
            new VectorDocument($id3, new Vector([0.3, 0.7, 0.1])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(3, $result);

        $store->remove($id2->toRfc4122());

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(2, $result);

        $remainingIds = array_map(static fn (VectorDocument $doc) => $doc->getId(), $result);
        $this->assertNotContains($id2->toRfc4122(), $remainingIds);
        $this->assertContains($id1->toRfc4122(), $remainingIds);
        $this->assertContains($id3->toRfc4122(), $remainingIds);
    }

    public function testRemoveWithArrayOfIds()
    {
        $store = new Store(new ArrayAdapter());
        $store->setup();

        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $id3 = Uuid::v4();
        $id4 = Uuid::v4();

        $store->add([
            new VectorDocument($id1, new Vector([0.1, 0.1, 0.5])),
            new VectorDocument($id2, new Vector([0.7, -0.3, 0.0])),
            new VectorDocument($id3, new Vector([0.3, 0.7, 0.1])),
            new VectorDocument($id4, new Vector([0.0, 0.1, 0.6])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(4, $result);

        $store->remove([$id2->toRfc4122(), $id4->toRfc4122()]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(2, $result);

        $remainingIds = array_map(static fn (VectorDocument $doc) => $doc->getId(), $result);
        $this->assertNotContains($id2->toRfc4122(), $remainingIds);
        $this->assertNotContains($id4->toRfc4122(), $remainingIds);
        $this->assertContains($id1->toRfc4122(), $remainingIds);
        $this->assertContains($id3->toRfc4122(), $remainingIds);
    }

    public function testRemoveNonExistentId()
    {
        $store = new Store(new ArrayAdapter());
        $store->setup();

        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $nonExistentId = Uuid::v4();

        $store->add([
            new VectorDocument($id1, new Vector([0.1, 0.1, 0.5])),
            new VectorDocument($id2, new Vector([0.7, -0.3, 0.0])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(2, $result);

        $store->remove($nonExistentId->toRfc4122());

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(2, $result);
    }

    public function testRemoveAllIds()
    {
        $store = new Store(new ArrayAdapter());
        $store->setup();

        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $id3 = Uuid::v4();

        $store->add([
            new VectorDocument($id1, new Vector([0.1, 0.1, 0.5])),
            new VectorDocument($id2, new Vector([0.7, -0.3, 0.0])),
            new VectorDocument($id3, new Vector([0.3, 0.7, 0.1])),
        ]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(3, $result);

        $store->remove([$id1->toRfc4122(), $id2->toRfc4122(), $id3->toRfc4122()]);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([0.0, 0.1, 0.6]))));
        $this->assertCount(0, $result);
    }

    public function testRemoveWithOptions()
    {
        $store = new Store(new ArrayAdapter());
        $store->setup();

        $id1 = Uuid::v4();

        $store->add([
            new VectorDocument($id1, new Vector([0.1, 0.1, 0.5])),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');

        $store->remove($id1->toRfc4122(), ['unsupported' => true]);
    }

    public function testStoreCanSearchUsingTextQuery()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['_text' => 'The quick brown fox'])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['_text' => 'jumps over the lazy dog'])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['_text' => 'Lorem ipsum dolor sit amet'])),
        ]);

        $result = iterator_to_array($store->query(new TextQuery('quick brown')));

        $this->assertCount(1, $result);
        $this->assertStringContainsString('quick brown', $result[0]->getMetadata()->getText());
    }

    public function testStoreCanSearchUsingTextQueryWithMaxItems()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['_text' => 'The word test appears here'])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['_text' => 'Another test document'])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['_text' => 'Yet another test entry'])),
        ]);

        $result = iterator_to_array($store->query(new TextQuery('test'), ['maxItems' => 2]));

        $this->assertCount(2, $result);
    }

    public function testStoreCanSearchUsingHybridQuery()
    {
        $store = new Store(new ArrayAdapter());
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $id3 = Uuid::v4();

        $store->add([
            new VectorDocument($id1, new Vector([0.1, 0.1, 0.5]), new Metadata(['_text' => 'space exploration'])),
            new VectorDocument($id2, new Vector([0.0, 0.1, 0.6]), new Metadata(['_text' => 'deep space mission'])),
            new VectorDocument($id3, new Vector([0.9, 0.9, 0.9]), new Metadata(['_text' => 'cooking recipes'])),
        ]);

        $queryVector = new Vector([0.0, 0.1, 0.6]);
        $result = iterator_to_array($store->query(new HybridQuery($queryVector, 'space', 0.7)));

        // Should find documents matching either vector similarity or text content
        $this->assertGreaterThanOrEqual(2, \count($result));

        // Check that space-related documents are in results
        $texts = array_map(static fn (VectorDocument $doc) => $doc->getMetadata()->getText(), $result);
        $this->assertContains('deep space mission', $texts);
    }

    public function testStoreCanSearchUsingHybridQueryWithDifferentSemanticRatios()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['_text' => 'vector match'])),
            new VectorDocument(Uuid::v4(), new Vector([0.0, 0.1, 0.6]), new Metadata(['_text' => 'exact text match'])),
        ]);

        $queryVector = new Vector([0.0, 0.1, 0.6]);

        // High semantic ratio (favor vector similarity)
        $resultHighSemantic = iterator_to_array($store->query(new HybridQuery($queryVector, 'exact text match', 0.9)));
        $this->assertNotEmpty($resultHighSemantic);

        // Low semantic ratio (favor text matching)
        $resultLowSemantic = iterator_to_array($store->query(new HybridQuery($queryVector, 'exact text match', 0.1)));
        $this->assertNotEmpty($resultLowSemantic);
    }

    public function testHybridQueryThrowsExceptionForInvalidSemanticRatio()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Semantic ratio must be between 0.0 and 1.0');

        new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'test', 1.5);
    }

    public function testStoreThrowsExceptionForUnsupportedQueryType()
    {
        $store = new Store(new ArrayAdapter());

        // Create a mock query type that Cache store doesn't support
        $unsupportedQuery = new class implements \Symfony\AI\Store\Query\QueryInterface {
        };

        $this->expectException(\Symfony\AI\Store\Exception\UnsupportedQueryTypeException::class);
        $this->expectExceptionMessageMatches('/not supported/');

        $store->query($unsupportedQuery);
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new ArrayAdapter());
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreSupportsTextQuery()
    {
        $store = new Store(new ArrayAdapter());
        $this->assertTrue($store->supports(TextQuery::class));
    }

    public function testStoreSupportsHybridQuery()
    {
        $store = new Store(new ArrayAdapter());
        $this->assertTrue($store->supports(HybridQuery::class));
    }
}
