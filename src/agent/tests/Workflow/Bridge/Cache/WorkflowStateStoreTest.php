<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Bridge\Cache;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\Bridge\Cache\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;

final class WorkflowStateStoreTest extends TestCase
{
    public function testSave()
    {
        $state = new WorkflowState('test-id', ['key' => 'value']);

        $stateItem = $this->createMock(CacheItemInterface::class);
        $stateItem->expects($this->once())->method('set')->with($this->isType('string'));
        $stateItem->expects($this->once())->method('expiresAfter')->with(86400);

        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(false);
        $indexItem->expects($this->once())->method('set')->with(['test-id']);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturnMap([
            ['_workflow_state_test-id', $stateItem],
            ['_workflow_state_index', $indexItem],
        ]);
        $cache->expects($this->exactly(2))->method('save');

        $store = new WorkflowStateStore($cache);
        $store->save($state);
    }

    public function testLoadSuccess()
    {
        $json = '{"id":"test-id","data":{"key":"value"},"completed_places":[],"current_place":null}';

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($json);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);

        $store = new WorkflowStateStore($cache);
        $loaded = $store->load('test-id');

        $this->assertSame('test-id', $loaded->getId());
        $this->assertSame('value', $loaded->get('key'));
    }

    public function testLoadThrowsWhenNotFound()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);

        $store = new WorkflowStateStore($cache);

        $this->expectException(WorkflowStateNotFoundException::class);

        $store->load('nonexistent');
    }

    public function testHas()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);

        $store = new WorkflowStateStore($cache);

        $this->assertTrue($store->has('test-id'));
    }

    public function testDelete()
    {
        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(true);
        $indexItem->method('get')->willReturn(['test-id', 'other-id']);
        $indexItem->expects($this->once())->method('set')->with(['other-id']);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('_workflow_state_index')->willReturn($indexItem);
        $cache->expects($this->once())->method('deleteItem')->with('_workflow_state_test-id');
        $cache->expects($this->once())->method('save')->with($indexItem);

        $store = new WorkflowStateStore($cache);
        $store->delete('test-id');
    }

    public function testDropOnlyRemovesIndexedWorkflowKeys()
    {
        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(true);
        $indexItem->method('get')->willReturn(['id-a', 'id-b']);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('_workflow_state_index')->willReturn($indexItem);
        $cache->expects($this->never())->method('clear');
        $cache->expects($this->once())
            ->method('deleteItems')
            ->with(['_workflow_state_id-a', '_workflow_state_id-b', '_workflow_state_index']);

        $store = new WorkflowStateStore($cache);
        $store->drop();
    }

    public function testListIsEmptyOnFreshStore()
    {
        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('_workflow_state_index')->willReturn($indexItem);

        $store = new WorkflowStateStore($cache);

        $this->assertSame([], array_values(iterator_to_array($store->list())));
    }

    public function testListYieldsBothIdsAfterTwoSaves()
    {
        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(true);
        $indexItem->method('get')->willReturn(['id-a', 'id-b']);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('_workflow_state_index')->willReturn($indexItem);

        $store = new WorkflowStateStore($cache);

        $ids = array_values(iterator_to_array($store->list()));
        sort($ids);

        $this->assertSame(['id-a', 'id-b'], $ids);
    }

    public function testListYieldsOnlyRemainingIdAfterDelete()
    {
        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(true);
        $indexItem->method('get')->willReturn(['id-b']);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->with('_workflow_state_index')->willReturn($indexItem);

        $store = new WorkflowStateStore($cache);

        $ids = array_values(iterator_to_array($store->list()));

        $this->assertSame(['id-b'], $ids);
    }
}
