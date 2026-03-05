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

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('set')->with($this->isType('string'));
        $item->expects($this->once())->method('expiresAfter')->with(86400);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('getItem')
            ->with('_workflow_state_test-id')
            ->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item);

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
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('deleteItem')
            ->with('_workflow_state_test-id');

        $store = new WorkflowStateStore($cache);
        $store->delete('test-id');
    }
}
