<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Profiler\TraceableStore;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\InMemory\Store;
use Symfony\AI\Store\Query\VectorQuery;
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
        ], $traceableStore->calls);
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
        ], $traceableStore->calls);
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
        ], $traceableStore->calls);
    }
}
