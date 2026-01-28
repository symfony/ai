<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\Cache;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Cache\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(3, $result);

        $store->remove($id2->toRfc4122());

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(2, $result);

        $remainingIds = array_map(static fn (VectorDocument $doc) => $doc->id->toRfc4122(), $result);
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(4, $result);

        $store->remove([$id2->toRfc4122(), $id4->toRfc4122()]);

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(2, $result);

        $remainingIds = array_map(static fn (VectorDocument $doc) => $doc->id->toRfc4122(), $result);
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(2, $result);

        $store->remove($nonExistentId->toRfc4122());

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
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

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
        $this->assertCount(3, $result);

        $store->remove([$id1->toRfc4122(), $id2->toRfc4122(), $id3->toRfc4122()]);

        $result = iterator_to_array($store->query(new Vector([0.0, 0.1, 0.6])));
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
}
