<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\InMemory;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;

final class WorkflowStateStoreTest extends TestCase
{
    public function testSaveAndLoad()
    {
        $store = new WorkflowStateStore();
        $state = new WorkflowState('test-id', ['key' => 'value']);

        $store->save($state);

        $loaded = $store->load('test-id');
        $this->assertSame('test-id', $loaded->getId());
        $this->assertSame('value', $loaded->get('key'));
    }

    public function testLoadThrowsWhenNotFound()
    {
        $store = new WorkflowStateStore();

        $this->expectException(WorkflowStateNotFoundException::class);
        $this->expectExceptionMessage('Workflow state with id "nonexistent" not found.');

        $store->load('nonexistent');
    }

    public function testHas()
    {
        $store = new WorkflowStateStore();

        $this->assertFalse($store->has('test-id'));

        $store->save(new WorkflowState('test-id'));

        $this->assertTrue($store->has('test-id'));
    }

    public function testDelete()
    {
        $store = new WorkflowStateStore();
        $store->save(new WorkflowState('test-id'));

        $this->assertTrue($store->has('test-id'));

        $store->delete('test-id');

        $this->assertFalse($store->has('test-id'));
    }

    public function testReset()
    {
        $store = new WorkflowStateStore();
        $store->save(new WorkflowState('id1'));
        $store->save(new WorkflowState('id2'));

        $store->reset();

        $this->assertFalse($store->has('id1'));
        $this->assertFalse($store->has('id2'));
    }
}
