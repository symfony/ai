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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchStatus;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchJobTest extends TestCase
{
    public function testIsCompleteReturnsTrueForCompletedStatus()
    {
        $job = new BatchJob('batch-1', BatchStatus::COMPLETED);

        $this->assertTrue($job->isComplete());
    }

    #[DataProvider('provideNonCompletedStatuses')]
    public function testIsCompleteReturnsFalseForNonCompletedStatus(BatchStatus $status)
    {
        $job = new BatchJob('batch-1', $status);

        $this->assertFalse($job->isComplete());
    }

    public static function provideNonCompletedStatuses(): iterable
    {
        yield 'processing' => [BatchStatus::PROCESSING];
        yield 'failed' => [BatchStatus::FAILED];
        yield 'cancelled' => [BatchStatus::CANCELLED];
        yield 'expired' => [BatchStatus::EXPIRED];
    }

    public function testIsProcessingReturnsTrueForProcessingStatus()
    {
        $job = new BatchJob('batch-1', BatchStatus::PROCESSING);

        $this->assertTrue($job->isProcessing());
    }

    public function testIsFailedReturnsTrueForFailedStatus()
    {
        $job = new BatchJob('batch-1', BatchStatus::FAILED);

        $this->assertTrue($job->isFailed());
    }

    #[DataProvider('provideTerminalStatuses')]
    public function testIsTerminalReturnsTrueForTerminalStatuses(BatchStatus $status)
    {
        $job = new BatchJob('batch-1', $status);

        $this->assertTrue($job->isTerminal());
    }

    public static function provideTerminalStatuses(): iterable
    {
        yield 'completed' => [BatchStatus::COMPLETED];
        yield 'failed' => [BatchStatus::FAILED];
        yield 'cancelled' => [BatchStatus::CANCELLED];
        yield 'expired' => [BatchStatus::EXPIRED];
    }

    public function testIsTerminalReturnsFalseForProcessing()
    {
        $job = new BatchJob('batch-1', BatchStatus::PROCESSING);

        $this->assertFalse($job->isTerminal());
    }

    public function testGettersReturnConstructorValues()
    {
        $job = new BatchJob(
            id: 'batch-abc',
            status: BatchStatus::COMPLETED,
            totalCount: 10,
            processedCount: 8,
            failedCount: 2,
            outputFileId: 'file-output',
            errorFileId: 'file-error',
        );

        $this->assertSame('batch-abc', $job->getId());
        $this->assertSame(BatchStatus::COMPLETED, $job->getStatus());
        $this->assertSame(10, $job->getTotalCount());
        $this->assertSame(8, $job->getProcessedCount());
        $this->assertSame(2, $job->getFailedCount());
        $this->assertSame('file-output', $job->getOutputFileId());
        $this->assertSame('file-error', $job->getErrorFileId());
    }
}
