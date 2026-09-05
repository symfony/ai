<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\FiberToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class FiberToolExecutorTest extends TestCase
{
    public function testExecuteReturnsEmptyArrayForNoToolCalls()
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->never())->method('execute');

        $executor = new FiberToolExecutor($toolbox);

        $this->assertSame([], $this->collect($executor->execute([])));
    }

    public function testExecuteCallsToolboxOncePerToolCall()
    {
        $toolCall1 = new ToolCall('id1', 'tool_one');
        $toolCall2 = new ToolCall('id2', 'tool_two');
        $result1 = new ToolResult($toolCall1, 'result_one');
        $result2 = new ToolResult($toolCall2, 'result_two');

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls($result1, $result2);

        $executor = new FiberToolExecutor($toolbox);
        $results = $this->collect($executor->execute([$toolCall1, $toolCall2]));

        $this->assertCount(2, $results);
        $this->assertContains($result1, $results);
        $this->assertContains($result2, $results);
    }

    public function testExecutePreservesOrderOfResults()
    {
        $toolCalls = [
            new ToolCall('id1', 'first_tool'),
            new ToolCall('id2', 'second_tool'),
            new ToolCall('id3', 'third_tool'),
        ];

        $expectedResults = [
            new ToolResult($toolCalls[0], 'first'),
            new ToolResult($toolCalls[1], 'second'),
            new ToolResult($toolCalls[2], 'third'),
        ];

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnOnConsecutiveCalls(...$expectedResults);

        $executor = new FiberToolExecutor($toolbox);
        $results = $this->collect($executor->execute($toolCalls));

        // The executor guarantees results are returned in the same order as the given tool calls,
        // regardless of the order in which the fibers terminate.
        $this->assertSame($expectedResults[0], $results[0]);
        $this->assertSame($expectedResults[1], $results[1]);
        $this->assertSame($expectedResults[2], $results[2]);
    }

    public function testExecuteHandlesFiberSuspension()
    {
        $toolCall = new ToolCall('id1', 'fiber_suspending_tool');
        $expectedResult = new ToolResult($toolCall, 'fiber_result');

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static function () use ($expectedResult): ToolResult {
                \Fiber::suspend();

                return $expectedResult;
            });

        $executor = new FiberToolExecutor($toolbox);
        $results = $this->collect($executor->execute([$toolCall]));

        $this->assertCount(1, $results);
        $this->assertSame($expectedResult, $results[0]);
    }

    public function testExecuteHandlesMultipleFiberSuspensions()
    {
        $toolCall = new ToolCall('id1', 'multi_suspend_tool');
        $expectedResult = new ToolResult($toolCall, 'multi_suspend_result');

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static function () use ($expectedResult): ToolResult {
                \Fiber::suspend();
                \Fiber::suspend();
                \Fiber::suspend();

                return $expectedResult;
            });

        $executor = new FiberToolExecutor($toolbox);
        $results = $this->collect($executor->execute([$toolCall]));

        $this->assertCount(1, $results);
        $this->assertSame($expectedResult, $results[0]);
    }

    public function testAllFibersStartedBeforeResultsCollected()
    {
        // Verify cooperative-concurrency behaviour: every fiber is started before any result
        // is collected. We track the order in which fibers begin and complete.
        $startLog = [];

        $toolCalls = [
            new ToolCall('fiber_a', 'fiber_a'),
            new ToolCall('fiber_b', 'fiber_b'),
            new ToolCall('fiber_c', 'fiber_c'),
        ];

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static function (ToolCall $toolCall) use (&$startLog): ToolResult {
                $startLog[] = $toolCall->getId();
                \Fiber::suspend();

                return new ToolResult($toolCall, $toolCall->getId().'_result');
            });

        $executor = new FiberToolExecutor($toolbox);
        $results = $this->collect($executor->execute($toolCalls));

        // All starts must have happened before any end because all fibers are launched first.
        $this->assertSame(['fiber_a', 'fiber_b', 'fiber_c'], $startLog);
        $this->assertCount(3, $results);
    }

    public function testRoundRobinSchedulingInterleavesFibers()
    {
        // Fiber A suspends once; fiber B never suspends. With round-robin scheduling,
        // both fibers start, then in the first round B terminates and A gets one resume
        // before it also terminates. The execution log proves true interleaving.
        $log = [];

        $toolCallA = new ToolCall('a', 'fiber_a');
        $toolCallB = new ToolCall('b', 'fiber_b');

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static function (ToolCall $toolCall) use (&$log): ToolResult {
                $log[] = $toolCall->getId().':start';
                if ('a' === $toolCall->getId()) {
                    \Fiber::suspend();
                }
                $log[] = $toolCall->getId().':end';

                return new ToolResult($toolCall, $toolCall->getId().'_result');
            });

        $executor = new FiberToolExecutor($toolbox);
        $results = $this->collect($executor->execute([$toolCallA, $toolCallB]));

        // Both fibers start first (during the start phase), then the round-robin
        // gives fiber_b a chance to complete while fiber_a is still suspended.
        $this->assertSame(['a:start', 'b:start', 'b:end', 'a:end'], $log);
        // Despite fiber_b terminating first, results stay in the tool call order.
        $this->assertCount(2, $results);
        $this->assertSame($toolCallA, $results[0]->getToolCall());
        $this->assertSame($toolCallB, $results[1]->getToolCall());
    }

    public function testExecuteReportsAProgressUpdatePerToolCall()
    {
        $toolCall1 = new ToolCall('id1', 'tool_one');
        $toolCall2 = new ToolCall('id2', 'tool_two');

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static fn (ToolCall $toolCall): ToolResult => new ToolResult($toolCall, 'result'));

        $executor = new FiberToolExecutor($toolbox);
        $updates = iterator_to_array($executor->execute([$toolCall1, $toolCall2]));

        $this->assertCount(2, $updates);
        $this->assertContainsOnlyInstancesOf(Progress::class, $updates);
        $this->assertSame('tool_call', $updates[0]->getStage());
        $this->assertSame($toolCall1, $updates[0]->getPayload());
        $this->assertSame($toolCall2, $updates[1]->getPayload());
    }

    public function testExecutePropagatesToolboxExceptions()
    {
        $toolCall = new ToolCall('id1', 'broken_tool');

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willThrowException(new \RuntimeException('Tool exploded.'));

        $executor = new FiberToolExecutor($toolbox);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool exploded.');

        $this->collect($executor->execute([$toolCall]));
    }

    /**
     * @param \Generator<int, mixed, mixed, ToolResult[]> $execution
     *
     * @return ToolResult[]
     */
    private function collect(\Generator $execution): array
    {
        iterator_to_array($execution);

        return $execution->getReturn();
    }
}
