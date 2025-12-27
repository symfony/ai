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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetup()
    {
        $store = new Store(new ArrayAdapter());
        $store->setup();

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(3, $result);

        $store->drop();

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(3, $result);
        $this->assertSame([0.1, 0.1, 0.5], $result[0]->vector->getData());

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
        ]);

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(6, $result);
        $this->assertSame([0.1, 0.1, 0.5], $result[0]->vector->getData());
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(5, $result);
        $this->assertSame([0.0, 0.1, 0.6], $result[0]->vector->getData());
        $this->assertSame([0.1, 0.1, 0.5], $result[1]->vector->getData());
        $this->assertSame([0.3, 0.1, 0.6], $result[2]->vector->getData());
        $this->assertSame([0.3, 0.7, 0.1], $result[3]->vector->getData());
        $this->assertSame([0.7, -0.3, 0.0], $result[4]->vector->getData());
    }

    public function testStoreCanSearchUsingCosineDistanceWithMaxItems()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
        ]);

        $this->assertCount(1, iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6]), [
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

        $result = iterator_to_array($store->query(new Vector([1.2, 2.3, 3.4])));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->vector->getData());
    }

    public function testStoreCanSearchUsingEuclideanDistance()
    {
        $store = new Store(new ArrayAdapter(), new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE));
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([1.0, 5.0, 7.0])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0])),
        ]);

        $result = iterator_to_array($store->query(new Vector([1.2, 2.3, 3.4])));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->vector->getData());
    }

    public function testStoreCanSearchUsingManhattanDistance()
    {
        $store = new Store(new ArrayAdapter(), new DistanceCalculator(DistanceStrategy::MANHATTAN_DISTANCE));
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 5.0, 7.0])),
        ]);

        $result = iterator_to_array($store->query(new Vector([1.2, 2.3, 3.4])));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->vector->getData());
    }

    public function testStoreCanSearchUsingChebyshevDistance()
    {
        $store = new Store(new ArrayAdapter(), new DistanceCalculator(DistanceStrategy::CHEBYSHEV_DISTANCE));
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 5.0, 7.0])),
        ]);

        $result = iterator_to_array($store->query(new Vector([1.2, 2.3, 3.4])));

        $this->assertCount(2, $result);
        $this->assertSame([1.0, 2.0, 3.0], $result[0]->vector->getData());
    }

    public function testStoreCanSearchWithFilter()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['category' => 'products', 'enabled' => true])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['category' => 'articles', 'enabled' => true])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['category' => 'products', 'enabled' => false])),
        ]);

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6]), [
            'filter' => fn (VectorDocument $doc) => 'products' === $doc->metadata['category'],
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('products', $result[0]->metadata['category']);
        $this->assertSame('products', $result[1]->metadata['category']);
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6]), [
            'filter' => fn (VectorDocument $doc) => 'products' === $doc->metadata['category'],
            'maxItems' => 2,
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('products', $result[0]->metadata['category']);
        $this->assertSame('products', $result[1]->metadata['category']);
    }

    public function testStoreCanSearchWithComplexFilter()
    {
        $store = new Store(new ArrayAdapter());
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5]), new Metadata(['price' => 100, 'stock' => 5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0]), new Metadata(['price' => 200, 'stock' => 0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1]), new Metadata(['price' => 50, 'stock' => 10])),
        ]);

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6]), [
            'filter' => fn (VectorDocument $doc) => $doc->metadata['price'] <= 150 && $doc->metadata['stock'] > 0,
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6]), [
            'filter' => fn (VectorDocument $doc) => 'S' === $doc->metadata['options']['size'],
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('S', $result[0]->metadata['options']['size']);
        $this->assertSame('S', $result[1]->metadata['options']['size']);
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
        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6]), [
            'filter' => fn (VectorDocument $doc) => \in_array($doc->metadata['brand'] ?? '', $allowedBrands, true),
        ]));

        $this->assertCount(2, $result);
    }
}
