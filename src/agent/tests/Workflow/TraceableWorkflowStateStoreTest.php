<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\TraceableWorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class TraceableWorkflowStateStoreTest extends TestCase
{
    public function testCollectsDataForSetup()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->setup();

        $this->assertCount(1, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'setup',
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);
    }

    public function testCollectsDataForDrop()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->drop();

        $this->assertCount(1, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'drop',
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);
    }

    public function testCollectsDataForSave()
    {
        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState(Uuid::v7()->toRfc4122());

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);

        $this->assertCount(1, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);
    }

    public function testCollectsDataForLoad()
    {
        $uuid = Uuid::v7()->toRfc4122();

        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState($uuid);

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);
        $traceableWorkflowStateStore->load($uuid);

        $this->assertCount(2, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);
        $this->assertEquals([
            'method' => 'load',
            'id' => $uuid,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[1]);
    }

    public function testCollectsDataForHas()
    {
        $uuid = Uuid::v7()->toRfc4122();

        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState($uuid);

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);
        $traceableWorkflowStateStore->has($uuid);

        $this->assertCount(2, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);
        $this->assertEquals([
            'method' => 'has',
            'id' => $uuid,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[1]);
    }

    public function testCollectsDataForDelete()
    {
        $uuid = Uuid::v7()->toRfc4122();

        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState($uuid);

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);
        $traceableWorkflowStateStore->delete($uuid);

        $this->assertCount(2, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);
        $this->assertEquals([
            'method' => 'delete',
            'id' => $uuid,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[1]);
    }

    public function testCollectsDataForReset()
    {
        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState(Uuid::v7()->toRfc4122());

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);

        $this->assertCount(1, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);

        $traceableWorkflowStateStore->reset();
        $this->assertCount(0, $traceableWorkflowStateStore->getCalls());
    }

    public function testCollectsDataForList()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->list();

        $this->assertCount(1, $traceableWorkflowStateStore->getCalls());
        $this->assertEquals([
            'method' => 'list',
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->getCalls()[0]);
    }

    public function testListDelegatesToDecoratedStore()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $inner = new WorkflowStateStore();
        $inner->save(new WorkflowState('id-x'));
        $inner->save(new WorkflowState('id-y'));

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore($inner, $clock);

        $ids = array_values(iterator_to_array($traceableWorkflowStateStore->list()));
        sort($ids);

        $this->assertSame(['id-x', 'id-y'], $ids);
    }

    public function testGetDecoratedStoreReturnsWrappedInstance()
    {
        $inner = new WorkflowStateStore();
        $traceableWorkflowStateStore = new TraceableWorkflowStateStore($inner);

        $this->assertSame($inner, $traceableWorkflowStateStore->getDecoratedStore());
    }

    public function testListThrowsRuntimeExceptionWhenDecoratedStoreDoesNotSupportListing()
    {
        $nonListableStore = new class implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface {
            public function setup(): void
            {
            }

            public function drop(): void
            {
            }

            public function save(WorkflowStateInterface $state): void
            {
            }

            public function load(string $id): WorkflowStateInterface
            {
                throw new \LogicException('Not implemented.');
            }

            public function has(string $id): bool
            {
                return false;
            }

            public function delete(string $id): void
            {
            }
        };

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore($nonListableStore);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The decorated workflow state store does not support listing.');

        $traceableWorkflowStateStore->list();
    }
}
