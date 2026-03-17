<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Bridge\Redis;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\Bridge\Redis\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;

#[RequiresPhpExtension('redis')]
final class WorkflowStateStoreTest extends TestCase
{
    public function testSave()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('set')
            ->with(
                '_workflow_state:test-id',
                $this->isType('string'),
            );

        $store = new WorkflowStateStore($redis);
        $store->save(new WorkflowState('test-id', ['key' => 'value']));
    }

    public function testLoadSuccess()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->with('_workflow_state:test-id')
            ->willReturn('{"id":"test-id","data":{"key":"value"},"completed_places":[],"current_place":null}');

        $store = new WorkflowStateStore($redis);
        $state = $store->load('test-id');

        $this->assertSame('test-id', $state->getId());
        $this->assertSame(['key' => 'value'], $state->all());
    }

    public function testLoadThrowsWhenNotFound()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->with('_workflow_state:missing-id')
            ->willReturn(false);

        $store = new WorkflowStateStore($redis);

        $this->expectException(WorkflowStateNotFoundException::class);
        $store->load('missing-id');
    }

    public function testHasReturnsTrue()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('exists')
            ->with('_workflow_state:test-id')
            ->willReturn(1);

        $store = new WorkflowStateStore($redis);

        $this->assertTrue($store->has('test-id'));
    }

    public function testHasReturnsFalse()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('exists')
            ->with('_workflow_state:test-id')
            ->willReturn(0);

        $store = new WorkflowStateStore($redis);

        $this->assertFalse($store->has('test-id'));
    }

    public function testDelete()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('del')
            ->with('_workflow_state:test-id');

        $store = new WorkflowStateStore($redis);
        $store->delete('test-id');
    }

    public function testSetupPingsRedis()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('ping');

        $store = new WorkflowStateStore($redis);
        $store->setup();
    }

    public function testSetupThrowsOnOptions()
    {
        $redis = $this->createMock(\Redis::class);
        $store = new WorkflowStateStore($redis);

        $this->expectException(InvalidArgumentException::class);
        $store->setup(['foo' => 'bar']);
    }

    public function testCustomKeyPrefix()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('set')
            ->with(
                'custom:test-id',
                $this->isType('string'),
            );

        $store = new WorkflowStateStore($redis, 'custom:');
        $store->save(new WorkflowState('test-id'));
    }
}
