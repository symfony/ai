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
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\InMemory\Store;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\TraceableStore;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class TraceableStoreTest extends TestCase
{
    public function testStoreCanRetrieveDataOnNewDocuments()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableStore = new TraceableStore(new Store(), $clock);

        $document = new VectorDocument(Uuid::v7()->toRfc4122(), new Vector([0.1, 0.2, 0.3]));

        $traceableStore->add($document);

        $this->assertEquals([
            [
                'method' => 'add',
                'documents' => $document,
                'called_at' => $clock->now(),
            ],
        ], $traceableStore->getCalls());
    }

    public function testStoreCanRetrieveDataOnQuery()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableStore = new TraceableStore(new Store(), $clock);

        $query = new VectorQuery(new Vector([0.1, 0.2, 0.3]));

        $traceableStore->query($query);

        $this->assertEquals([
            [
                'method' => 'query',
                'query' => $query,
                'options' => [],
                'called_at' => $clock->now(),
            ],
        ], $traceableStore->getCalls());
    }

    public function testStoreCanRetrieveDataOnRemove()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableStore = new TraceableStore(new Store(), $clock);

        $uuid = Uuid::v7()->toRfc4122();

        $traceableStore->remove([$uuid]);

        $this->assertEquals([
            [
                'method' => 'remove',
                'ids' => [$uuid],
                'options' => [],
                'called_at' => $clock->now(),
            ],
        ], $traceableStore->getCalls());
    }

    public function testSetupDelegatesToManagedStore()
    {
        $innerStore = new class implements StoreInterface, ManagedStoreInterface {
            public bool $setupCalled = false;
            /** @var array<mixed> */
            public array $setupOptions = [];

            public function setup(array $options = []): void
            {
                $this->setupCalled = true;
                $this->setupOptions = $options;
            }

            public function drop(array $options = []): void
            {
            }

            public function add(VectorDocument|array $documents): void
            {
            }

            public function query(QueryInterface $query, array $options = []): iterable
            {
                return [];
            }

            public function remove(array|string $ids, array $options = []): void
            {
            }

            public function supports(string $queryClass): bool
            {
                return false;
            }
        };

        $traceableStore = new TraceableStore($innerStore);

        $traceableStore->setup(['foo' => 'bar']);

        $this->assertTrue($innerStore->setupCalled);
        $this->assertSame(['foo' => 'bar'], $innerStore->setupOptions);
    }

    public function testSetupDoesNothingWhenInnerStoreIsNotManaged()
    {
        $innerStore = $this->createMock(StoreInterface::class);

        $traceableStore = new TraceableStore($innerStore);

        $traceableStore->setup();

        $this->addToAssertionCount(1);
    }

    public function testDropDelegatesToManagedStore()
    {
        $innerStore = new class implements StoreInterface, ManagedStoreInterface {
            public bool $dropCalled = false;
            /** @var array<mixed> */
            public array $dropOptions = [];

            public function setup(array $options = []): void
            {
            }

            public function drop(array $options = []): void
            {
                $this->dropCalled = true;
                $this->dropOptions = $options;
            }

            public function add(VectorDocument|array $documents): void
            {
            }

            public function query(QueryInterface $query, array $options = []): iterable
            {
                return [];
            }

            public function remove(array|string $ids, array $options = []): void
            {
            }

            public function supports(string $queryClass): bool
            {
                return false;
            }
        };

        $traceableStore = new TraceableStore($innerStore);

        $traceableStore->drop(['foo' => 'bar']);

        $this->assertTrue($innerStore->dropCalled);
        $this->assertSame(['foo' => 'bar'], $innerStore->dropOptions);
    }

    public function testDropDoesNothingWhenInnerStoreIsNotManaged()
    {
        $innerStore = $this->createMock(StoreInterface::class);

        $traceableStore = new TraceableStore($innerStore);

        $traceableStore->drop();

        $this->addToAssertionCount(1);
    }
}
