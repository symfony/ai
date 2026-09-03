<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Approval\Checkpoint;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpoint;
use Symfony\AI\Agent\Approval\Checkpoint\InMemoryCheckpointStore;

final class InMemoryCheckpointStoreTest extends TestCase
{
    public function testSaveAndGet()
    {
        $store = new InMemoryCheckpointStore();

        $checkpoint = new ExecutionCheckpoint(
            id: 'cp-1',
            agentName: 'agent-1',
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $store->save($checkpoint);

        $this->assertSame($checkpoint, $store->get('cp-1'));
        $this->assertNull($store->get('unknown'));
        $this->assertCount(1, $store->all());
    }

    public function testExpiredCheckpointIsIgnoredAndRemoved()
    {
        $store = new InMemoryCheckpointStore();

        $expired = new ExecutionCheckpoint(
            id: 'cp-expired',
            agentName: 'agent-1',
            expiresAt: new \DateTimeImmutable('-10 seconds'),
        );

        $store->save($expired);

        $this->assertNull($store->get('cp-expired'));
        $this->assertCount(0, $store->all());
    }

    public function testRemove()
    {
        $store = new InMemoryCheckpointStore();

        $checkpoint = new ExecutionCheckpoint(id: 'cp-1');
        $store->save($checkpoint);
        $this->assertNotNull($store->get('cp-1'));

        $store->remove('cp-1');
        $this->assertNull($store->get('cp-1'));
    }
}
