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
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Workflow\Step\ParallelStepExecutor;
use Symfony\AI\Agent\Workflow\Step\SequentialStepExecutor;
use Symfony\AI\Agent\Workflow\Step\Step;
use Symfony\AI\Agent\Workflow\Transition\TransitionRegistry;
use Symfony\AI\Agent\Workflow\WorkflowExecutor;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;
use Symfony\AI\Platform\Result\ResultInterface;

final class WorkflowExecutorParallelTest extends TestCase
{
    private WorkflowStoreInterface $store;
    private TransitionRegistry $registry;
    private AgentInterface $agent;

    protected function setUp(): void
    {
        $this->store = $this->createMock(WorkflowStoreInterface::class);
        $this->registry = new TransitionRegistry();
        $this->agent = $this->createMock(AgentInterface::class);
    }

    public function testExecutorUsesParallelByDefault(): void
    {
        if (!\extension_loaded('fiber')) {
            $this->markTestSkipped('Fiber extension not available');
        }

        $executor = new WorkflowExecutor(
            $this->registry,
            $this->store,
            logger: new NullLogger()
        );

        // Use reflection to check the internal stepExecutor
        $reflection = new \ReflectionClass($executor);
        $property = $reflection->getProperty('stepExecutor');
        $property->setAccessible(true);
        $stepExecutor = $property->getValue($executor);

        $this->assertInstanceOf(ParallelStepExecutor::class, $stepExecutor);
        $this->assertTrue($stepExecutor->supportsParallel());
    }

    public function testExecutorUsesSequentialWhenFiberNotAvailable(): void
    {
        // Force sequential executor
        $executor = new WorkflowExecutor(
            $this->registry,
            $this->store,
            new SequentialStepExecutor(),
            logger: new NullLogger()
        );

        // Use reflection to check the internal stepExecutor
        $reflection = new \ReflectionClass($executor);
        $property = $reflection->getProperty('stepExecutor');
        $property->setAccessible(true);
        $stepExecutor = $property->getValue($executor);

        $this->assertInstanceOf(SequentialStepExecutor::class, $stepExecutor);
        $this->assertFalse($stepExecutor->supportsParallel());
    }

    public function testExecuteParallelSteps(): void
    {
        if (!\extension_loaded('fiber')) {
            $this->markTestSkipped('Fiber extension not available');
        }

        $executor = new WorkflowExecutor(
            $this->registry,
            $this->store,
            new ParallelStepExecutor(),
            logger: new NullLogger()
        );

        $result1 = $this->createMock(ResultInterface::class);
        $result2 = $this->createMock(ResultInterface::class);

        $executor->addStep(new Step('step1', static fn () => $result1));
        $executor->addStep(new Step('step2', static fn () => $result2));

        $state = new WorkflowState('test-id', 'start');

        $results = $executor->executeParallelSteps(['step1', 'step2'], $this->agent, $state);

        $this->assertCount(2, $results);
        $this->assertSame($result1, $results[0]);
        $this->assertSame($result2, $results[1]);
    }

    public function testExecuteParallelStepsThrowsForUnknownStep(): void
    {
        $executor = new WorkflowExecutor(
            $this->registry,
            $this->store,
            logger: new NullLogger()
        );

        $state = new WorkflowState('test-id', 'start');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Step "unknown" not found');

        $executor->executeParallelSteps(['unknown'], $this->agent, $state);
    }

    public function testParallelStepUsesParallelExecutor(): void
    {
        if (!\extension_loaded('fiber')) {
            $this->markTestSkipped('Fiber extension not available');
        }

        $this->store->expects($this->atLeastOnce())->method('save');

        $executor = new WorkflowExecutor(
            $this->registry,
            $this->store,
            new ParallelStepExecutor(),
            logger: new NullLogger()
        );

        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Test result');

        // Step marqué comme parallel
        $step = new Step('parallel-step', static fn () => $result, parallel: true);
        $executor->addStep($step);

        $state = new WorkflowState('test-id', 'parallel-step');

        $actualResult = $executor->execute($this->agent, $state);

        $this->assertSame($result, $actualResult);
    }

    public function testNonParallelStepUsesStandardExecution(): void
    {
        $this->store->expects($this->atLeastOnce())->method('save');

        $executor = new WorkflowExecutor(
            $this->registry,
            $this->store,
            new SequentialStepExecutor(),
            logger: new NullLogger()
        );

        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Test result');

        // Step NOT marqué comme parallel
        $step = new Step('sequential-step', static fn () => $result, parallel: false);
        $executor->addStep($step);

        $state = new WorkflowState('test-id', 'sequential-step');

        $actualResult = $executor->execute($this->agent, $state);

        $this->assertSame($result, $actualResult);
    }
}
