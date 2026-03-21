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
use Symfony\AI\Platform\Batch\BatchInput;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchPlatform;
use Symfony\AI\Platform\Batch\BatchResult;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\BatchClientInterface;
use Symfony\AI\Platform\Bridge\Anthropic\ModelCatalog;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchPlatformTest extends TestCase
{
    public function testSubmitBatchThrowsWhenNoClientSupportsModel()
    {
        $client = $this->createMock(BatchClientInterface::class);
        $client->method('supports')->willReturn(false);

        $platform = new BatchPlatform($client, new ModelCatalog());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No BatchClient registered for model "claude-sonnet-4-20250514".');

        $platform->submitBatch('claude-sonnet-4-20250514', [
            new BatchInput('req-1', new MessageBag(Message::ofUser('Hello'))),
        ]);
    }

    public function testSubmitBatchNormalizesInputsAndDelegatesToClient()
    {
        $capturedModel = null;
        $capturedRequests = null;

        $expectedJob = new BatchJob('batch-123', BatchStatus::PROCESSING, 1);

        $client = $this->createMock(BatchClientInterface::class);
        $client->method('supports')->willReturn(true);
        $client->expects($this->once())
            ->method('submitBatch')
            ->willReturnCallback(function (Model $model, iterable $requests) use (&$capturedModel, &$capturedRequests, $expectedJob) {
                $capturedModel = $model;
                $capturedRequests = iterator_to_array($requests);

                return $expectedJob;
            });

        $platform = new BatchPlatform($client, new ModelCatalog());
        $job = $platform->submitBatch('claude-sonnet-4-20250514', [
            new BatchInput('req-1', new MessageBag(Message::ofUser('What is the capital of France?'))),
        ]);

        $this->assertSame($expectedJob, $job);
        $this->assertCount(1, $capturedRequests);
        $this->assertSame('req-1', $capturedRequests[0]['id']);
        $this->assertIsArray($capturedRequests[0]['payload']);
        $this->assertArrayHasKey('messages', $capturedRequests[0]['payload']);
    }

    public function testGetBatchDelegatesToClient()
    {
        $expectedJob = new BatchJob('batch-456', BatchStatus::PROCESSING);

        $client = $this->createMock(BatchClientInterface::class);
        $client->expects($this->once())
            ->method('getBatch')
            ->with('batch-456')
            ->willReturn($expectedJob);

        $platform = new BatchPlatform($client, new ModelCatalog());

        $this->assertSame($expectedJob, $platform->getBatch('batch-456'));
    }

    public function testFetchResultsDelegatesToClient()
    {
        $job = new BatchJob('batch-789', BatchStatus::COMPLETED, 1, 1, 0, 'file-out');
        $expectedResult = BatchResult::success('req-1', 'Paris', 10, 3);

        $client = $this->createMock(BatchClientInterface::class);
        $client->expects($this->once())
            ->method('fetchResults')
            ->with($job)
            ->willReturn([$expectedResult]);

        $platform = new BatchPlatform($client, new ModelCatalog());
        $results = iterator_to_array($platform->fetchResults($job));

        $this->assertCount(1, $results);
        $this->assertSame($expectedResult, $results[0]);
    }

    public function testCancelBatchDelegatesToClient()
    {
        $expected = new BatchJob('batch-to-cancel', BatchStatus::PROCESSING);

        $client = $this->createMock(BatchClientInterface::class);
        $client->expects($this->once())
            ->method('cancelBatch')
            ->with('batch-to-cancel')
            ->willReturn($expected);

        $platform = new BatchPlatform($client, new ModelCatalog());
        $result = $platform->cancelBatch('batch-to-cancel');

        $this->assertSame($expected, $result);
    }
}
