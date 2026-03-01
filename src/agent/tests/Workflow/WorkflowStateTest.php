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
use Symfony\AI\Agent\Workflow\WorkflowError;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStatus;

final class WorkflowStateTest extends TestCase
{
    public function testConstruction(): void
    {
        $state = new WorkflowState(
            id: 'test-id',
            currentStep: 'start',
            context: ['key' => 'value'],
            metadata: ['meta' => 'data']
        );

        $this->assertSame('test-id', $state->getId());
        $this->assertSame('start', $state->getCurrentStep());
        $this->assertSame(['key' => 'value'], $state->getContext());
        $this->assertSame(['meta' => 'data'], $state->getMetadata());
        $this->assertSame(WorkflowStatus::PENDING, $state->getStatus());
        $this->assertEmpty($state->getErrors());
    }

    public function testSetCurrentStep(): void
    {
        $state = new WorkflowState('test-id', 'start');
        $originalUpdatedAt = $state->getUpdatedAt();

        usleep(1000); // Ensure time difference
        $state->setCurrentStep('processing');

        $this->assertSame('processing', $state->getCurrentStep());
        $this->assertGreaterThan($originalUpdatedAt, $state->getUpdatedAt());
    }

    public function testMergeContext(): void
    {
        $state = new WorkflowState('test-id', 'start', ['key1' => 'value1']);
        $state->mergeContext(['key2' => 'value2']);

        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $state->getContext());
    }

    public function testMergeContextOverridesExistingKeys(): void
    {
        $state = new WorkflowState('test-id', 'start', ['key' => 'old']);
        $state->mergeContext(['key' => 'new']);

        $this->assertSame(['key' => 'new'], $state->getContext());
    }

    public function testSetStatus(): void
    {
        $state = new WorkflowState('test-id', 'start');
        $state->setStatus(WorkflowStatus::RUNNING);

        $this->assertSame(WorkflowStatus::RUNNING, $state->getStatus());
    }

    public function testAddError(): void
    {
        $state = new WorkflowState('test-id', 'start');
        $error = new WorkflowError('Test error', 'start', 500);

        $state->addError($error);

        $this->assertCount(1, $state->getErrors());
        $this->assertSame($error, $state->getErrors()[0]);
    }

    public function testClearErrors(): void
    {
        $state = new WorkflowState('test-id', 'start');
        $state->addError(new WorkflowError('Error 1', 'start'));
        $state->addError(new WorkflowError('Error 2', 'start'));

        $this->assertCount(2, $state->getErrors());

        $state->clearErrors();

        $this->assertEmpty($state->getErrors());
    }

    public function testToArray(): void
    {
        $state = new WorkflowState(
            id: 'test-id',
            currentStep: 'processing',
            context: ['key' => 'value'],
            metadata: ['meta' => 'data'],
            status: WorkflowStatus::RUNNING
        );

        $error = new WorkflowError('Test error', 'processing', 500);
        $state->addError($error);

        $array = $state->toArray();

        $this->assertSame('test-id', $array['id']);
        $this->assertSame('processing', $array['currentStep']);
        $this->assertSame(['key' => 'value'], $array['context']);
        $this->assertSame(['meta' => 'data'], $array['metadata']);
        $this->assertSame('running', $array['status']);
        $this->assertCount(1, $array['errors']);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayHasKey('updatedAt', $array);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'test-id',
            'currentStep' => 'processing',
            'context' => ['key' => 'value'],
            'metadata' => ['meta' => 'data'],
            'status' => 'running',
            'errors' => [
                [
                    'message' => 'Test error',
                    'step' => 'processing',
                    'code' => 500,
                    'occurredAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
                    'context' => [],
                ],
            ],
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];

        $state = WorkflowState::fromArray($data);

        $this->assertSame('test-id', $state->getId());
        $this->assertSame('processing', $state->getCurrentStep());
        $this->assertSame(['key' => 'value'], $state->getContext());
        $this->assertSame(['meta' => 'data'], $state->getMetadata());
        $this->assertSame(WorkflowStatus::RUNNING, $state->getStatus());
        $this->assertCount(1, $state->getErrors());
    }

    public function testUpdatedAtChangesOnModification(): void
    {
        $state = new WorkflowState('test-id', 'start');
        $originalUpdatedAt = $state->getUpdatedAt();

        usleep(1000);
        $state->setContext(['new' => 'context']);

        $this->assertGreaterThan($originalUpdatedAt, $state->getUpdatedAt());
    }
}
