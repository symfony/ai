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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\FailoverStore;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

#[CoversClass(FailoverStore::class)]
#[UsesClass(VectorDocument::class)]
#[UsesClass(Vector::class)]
#[UsesClass(InMemoryStore::class)]
final class FailoverStoreTest extends TestCase
{
    public function testStoreCannotSetup()
    {
        $firstStore = $this->createMock(ManagedStoreInterface::class);
        $firstStore->expects($this->once())->method('setup')->willThrowException(new RuntimeException('foo'));

        $secondStore = $this->createMock(ManagedStoreInterface::class);
        $secondStore->expects($this->once())->method('setup')->willThrowException(new RuntimeException('foo'));

        $store = new FailoverStore([
            $firstStore,
            $secondStore,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No store available');
        $this->expectExceptionCode(0);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $firstStore = $this->createMock(ManagedStoreInterface::class);
        $firstStore->expects($this->once())->method('setup')->willThrowException(new RuntimeException('foo'));

        $store = new FailoverStore([
            $firstStore,
            new InMemoryStore(),
        ]);

        $store->setup();
    }

    public function testStoreCannotDrop()
    {
        $firstStore = $this->createMock(ManagedStoreInterface::class);
        $firstStore->expects($this->once())->method('drop')->willThrowException(new RuntimeException('foo'));

        $secondStore = $this->createMock(ManagedStoreInterface::class);
        $secondStore->expects($this->once())->method('drop')->willThrowException(new RuntimeException('foo'));

        $store = new FailoverStore([
            $firstStore,
            $secondStore,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No store available');
        $this->expectExceptionCode(0);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $firstStore = $this->createMock(ManagedStoreInterface::class);
        $firstStore->expects($this->once())->method('drop')->willThrowException(new RuntimeException('foo'));

        $store = new FailoverStore([
            $firstStore,
            new InMemoryStore(),
        ]);

        $store->drop();
    }

    public function testStoreCannotAdd()
    {
        $firstStore = $this->createMock(StoreInterface::class);
        $firstStore->expects($this->once())->method('add')->willThrowException(new RuntimeException('foo'));

        $secondStore = $this->createMock(StoreInterface::class);
        $secondStore->expects($this->once())->method('add')->willThrowException(new RuntimeException('foo'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $store = new FailoverStore([
            $firstStore,
            $secondStore,
        ], $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No store available');
        $this->expectExceptionCode(0);
        $store->add(
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
        );
    }

    public function testStoreCanAdd()
    {
        $firstStore = $this->createMock(StoreInterface::class);
        $firstStore->expects($this->once())->method('add')->willThrowException(new RuntimeException('foo'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $store = new FailoverStore([
            $firstStore,
            new InMemoryStore(),
        ], $logger);

        $store->add(
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
        );
    }

    public function testStoreCannotQuery()
    {
        $firstStore = $this->createMock(StoreInterface::class);
        $firstStore->expects($this->once())->method('query')->willThrowException(new RuntimeException('foo'));

        $secondStore = $this->createMock(StoreInterface::class);
        $secondStore->expects($this->once())->method('query')->willThrowException(new RuntimeException('foo'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $store = new FailoverStore([
            $firstStore,
            $secondStore,
        ], $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No store available');
        $this->expectExceptionCode(0);
        $store->query(new Vector([0.0, 0.1, 0.6]));
    }

    public function testStoreCanQuery()
    {
        $firstStore = $this->createMock(StoreInterface::class);
        $firstStore->expects($this->once())->method('query')->willThrowException(new RuntimeException('foo'));

        $secondStore = new InMemoryStore();
        $secondStore->add(
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.1, 0.5])),
            new VectorDocument(Uuid::v4(), new Vector([0.7, -0.3, 0.0])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.7, 0.1])),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $store = new FailoverStore([
            $firstStore,
            $secondStore,
        ], $logger);

        $documents = $store->query(new Vector([0.0, 0.1, 0.6]));

        $this->assertCount(3, $documents);
    }
}
