<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Vektor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Vektor\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupWithOptions()
    {
        $store = new Store(sys_get_temp_dir());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $store->setup([
            'foo' => 'bar',
        ]);
    }

    public function testStoreCannotDropUndefinedDirectory()
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('exists')->willReturn(false);
        $filesystem->expects($this->never())->method('remove');

        $store = new Store(sys_get_temp_dir(), filesystem: $filesystem);

        $store->drop();
    }

    public function testStoreCanDropExistingDirectory()
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('exists')->willReturn(true);
        $filesystem->expects($this->once())->method('remove');

        $store = new Store(sys_get_temp_dir(), filesystem: $filesystem);

        $store->drop();
    }

    public function testStoreCanOptimize()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $store->drop([
            'optimize' => true,
        ]);

        $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(0, iterator_to_array($results));

        $store->drop();
    }

    public function testStoreCannotClearWithOptions()
    {
        $store = new Store(sys_get_temp_dir());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $store->clear([
            'foo' => 'bar',
        ]);
    }

    public function testStoreCanClearAndStaysUsable()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])),
        ]);

        $store->clear();

        $this->assertCount(0, iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])))));

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.4, 0.5, 0.6])),
        ]);

        $this->assertCount(1, iterator_to_array($store->query(new VectorQuery(new Vector([0.4, 0.5, 0.6])))));

        $store->drop();
    }

    public function testStoreCanAddThenSearch()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])),
        ]);

        $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(1, iterator_to_array($results));

        $store->drop();
    }

    public function testStoreReturnsAsManyResultsAsTheLimit()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])),
            new VectorDocument(Uuid::v4(), new Vector([0.4, 0.5, 0.6])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, 0.8, 0.9])),
        ]);

        $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 2]);

        $this->assertCount(2, iterator_to_array($results));

        $store->drop();
    }

    public function testFindReturnsTheDocumentWithItsVectorAndMetadata()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $id = Uuid::v4()->toRfc4122();

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.7, 0.8, 0.9])),
            new VectorDocument($id, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Found'])),
        ]);

        $document = $store->find($id);

        $this->assertNotNull($document);
        $this->assertSame($id, $document->getId());
        $this->assertEqualsWithDelta([0.1, 0.2, 0.3], $document->getVector()->getData(), 0.0001);
        $this->assertSame(['title' => 'Found'], $document->getMetadata()->getArrayCopy());
        $this->assertNull($document->getScore());

        $store->drop();
    }

    public function testFindReturnsNullForAnUnknownId()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])));

        $this->assertNull($store->find(Uuid::v4()->toRfc4122()));

        $store->drop();
    }

    public function testFindReturnsNullForARemovedDocument()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $id = Uuid::v4()->toRfc4122();

        $store->add(new VectorDocument($id, new Vector([0.1, 0.2, 0.3])));
        $store->remove($id);

        $this->assertNull($store->find($id));

        $store->drop();
    }

    public function testFindSimilarLeavesTheDocumentOut()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $id = Uuid::v4()->toRfc4122();
        $nearestId = Uuid::v4()->toRfc4122();

        $store->add([
            new VectorDocument($id, new Vector([0.1, 0.2, 0.3])),
            new VectorDocument(Uuid::v4(), new Vector([0.9, -0.5, 0.0])),
            new VectorDocument($nearestId, new Vector([0.1, 0.2, 0.35])),
        ]);

        $results = iterator_to_array($store->findSimilar($id, ['limit' => 1]), false);

        $this->assertCount(1, $results);
        $this->assertSame($nearestId, $results[0]->getId());

        $store->drop();
    }

    public function testCountReturnsDocumentCount()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $this->assertCount(0, $store);

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])),
            new VectorDocument(Uuid::v4(), new Vector([0.4, 0.5, 0.6])),
        ]);

        $this->assertCount(2, $store);

        $store->drop();
    }
}
