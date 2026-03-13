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
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\AiBundle\Profiler\TraceableWorkflowStateStore;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class TraceableWorkflowStateStoreTest extends TestCase
{
    public function testCollectsDataForSetup()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->setup();

        $this->assertCount(1, $traceableWorkflowStateStore->calls);
        $this->assertEquals([
            'method' => 'setup',
            'options' => [],
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[0]);
    }

    public function testCollectsDataForDrop()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->drop();

        $this->assertCount(1, $traceableWorkflowStateStore->calls);
        $this->assertEquals([
            'method' => 'drop',
            'options' => [],
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[0]);
    }

    public function testCollectsDataForSave()
    {
        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState(Uuid::v7()->toRfc4122());

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);

        $this->assertCount(1, $traceableWorkflowStateStore->calls);
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[0]);
    }

    public function testCollectsDataForLoad()
    {
        $uuid = Uuid::v7()->toRfc4122();

        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState($uuid);

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);
        $traceableWorkflowStateStore->load($uuid);

        $this->assertCount(2, $traceableWorkflowStateStore->calls);
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[0]);
        $this->assertEquals([
            'method' => 'load',
            'id' => $uuid,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[1]);
    }

    public function testCollectsDataForHas()
    {
        $uuid = Uuid::v7()->toRfc4122();

        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState($uuid);

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);
        $traceableWorkflowStateStore->has($uuid);

        $this->assertCount(2, $traceableWorkflowStateStore->calls);
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[0]);
        $this->assertEquals([
            'method' => 'has',
            'id' => $uuid,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[1]);
    }

    public function testCollectsDataForDelete()
    {
        $uuid = Uuid::v7()->toRfc4122();

        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState($uuid);

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);
        $traceableWorkflowStateStore->delete($uuid);

        $this->assertCount(2, $traceableWorkflowStateStore->calls);
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[0]);
        $this->assertEquals([
            'method' => 'delete',
            'id' => $uuid,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[1]);
    }

    public function testCollectsDataForReset()
    {
        $clock = new MockClock('2020-01-01 10:00:00');
        $state = new WorkflowState(Uuid::v7()->toRfc4122());

        $traceableWorkflowStateStore = new TraceableWorkflowStateStore(new WorkflowStateStore(), $clock);

        $traceableWorkflowStateStore->save($state);

        $this->assertCount(1, $traceableWorkflowStateStore->calls);
        $this->assertEquals([
            'method' => 'save',
            'state' => $state,
            'called_at' => $clock->now(),
        ], $traceableWorkflowStateStore->calls[0]);

        $traceableWorkflowStateStore->reset();
        $this->assertCount(0, $traceableWorkflowStateStore->calls);
    }
}
