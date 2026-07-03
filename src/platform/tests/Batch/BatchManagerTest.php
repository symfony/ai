<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Batch;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Batch\BatchClientInterface;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchManager;
use Symfony\AI\Platform\Batch\BatchResult;
use Symfony\AI\Platform\Batch\BatchStatus;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchManagerTest extends TestCase
{
    public function testRefreshDelegatesToClientById()
    {
        $refreshed = new BatchJob('batch-1', BatchStatus::COMPLETED);

        $client = $this->createMock(BatchClientInterface::class);
        $client->expects($this->once())
            ->method('getBatch')
            ->with('batch-1')
            ->willReturn($refreshed);

        $manager = new BatchManager($client);

        $this->assertSame($refreshed, $manager->refresh(new BatchJob('batch-1', BatchStatus::PROCESSING)));
    }

    public function testCancelDelegatesToClientById()
    {
        $cancelled = new BatchJob('batch-1', BatchStatus::CANCELLED);

        $client = $this->createMock(BatchClientInterface::class);
        $client->expects($this->once())
            ->method('cancelBatch')
            ->with('batch-1')
            ->willReturn($cancelled);

        $manager = new BatchManager($client);

        $this->assertSame($cancelled, $manager->cancel(new BatchJob('batch-1', BatchStatus::PROCESSING)));
    }

    public function testCanFetchResultsDelegatesToClient()
    {
        $job = new BatchJob('batch-1', BatchStatus::COMPLETED);

        $client = $this->createMock(BatchClientInterface::class);
        $client->expects($this->once())
            ->method('canFetchResults')
            ->with($job)
            ->willReturn(true);

        $manager = new BatchManager($client);

        $this->assertTrue($manager->canFetchResults($job));
    }

    public function testFetchResultsDelegatesToClient()
    {
        $job = new BatchJob('batch-1', BatchStatus::COMPLETED);
        $results = [BatchResult::success('req-1', 'Paris'), BatchResult::success('req-2', 'Berlin')];

        $client = $this->createMock(BatchClientInterface::class);
        $client->expects($this->once())
            ->method('fetchResults')
            ->with($job)
            ->willReturn($results);

        $manager = new BatchManager($client);

        $this->assertSame($results, $manager->fetchResults($job));
    }
}
