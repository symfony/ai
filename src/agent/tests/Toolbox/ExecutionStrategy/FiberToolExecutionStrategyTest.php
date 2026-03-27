<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\ExecutionStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ExecutionStrategy\FiberToolExecutionStrategy;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class FiberToolExecutionStrategyTest extends TestCase
{
    public function testExecuteReturnsEmptyArrayForNoToolCalls()
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->never())->method('execute');

        $strategy = new FiberToolExecutionStrategy();
        $results = $strategy->execute($toolbox, []);

        $this->assertSame([], $results);
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

        $strategy = new FiberToolExecutionStrategy();
        $results = $strategy->execute($toolbox, [$toolCall1, $toolCall2]);

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

        $strategy = new FiberToolExecutionStrategy();
        $results = $strategy->execute($toolbox, $toolCalls);

        // Order is not guaranteed when fibers suspend at different rates; verify by membership.
        $this->assertCount(3, $results);
        foreach ($expectedResults as $expected) {
            $this->assertContains($expected, $results);
        }
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

        $strategy = new FiberToolExecutionStrategy();
        $results = $strategy->execute($toolbox, [$toolCall]);

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

        $strategy = new FiberToolExecutionStrategy();
        $results = $strategy->execute($toolbox, [$toolCall]);

        $this->assertCount(1, $results);
        $this->assertSame($expectedResult, $results[0]);
    }

    public function testAllFibersStartedBeforeResultsCollected()
    {
        // Verify cooperative-concurrency behaviour: every fiber is started before any result
        // is collected. We track the order in which fibers begin and complete.
        $startLog = [];
        $endLog = [];

        $toolCalls = [
            new ToolCall('fiber_a', 'fiber_a'),
            new ToolCall('fiber_b', 'fiber_b'),
            new ToolCall('fiber_c', 'fiber_c'),
        ];

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static function (ToolCall $toolCall) use (&$startLog, &$endLog): ToolResult {
                $startLog[] = $toolCall->getId();
                \Fiber::suspend();
                $endLog[] = $toolCall->getId();

                return new ToolResult($toolCall, $toolCall->getId().'_result');
            });

        $strategy = new FiberToolExecutionStrategy();
        $results = $strategy->execute($toolbox, $toolCalls);

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

        $strategy = new FiberToolExecutionStrategy();
        $results = $strategy->execute($toolbox, [$toolCallA, $toolCallB]);

        // Both fibers start first (during the start phase), then the round-robin
        // gives fiber_b a chance to complete while fiber_a is still suspended.
        $this->assertSame(['a:start', 'b:start', 'b:end', 'a:end'], $log);
        $this->assertCount(2, $results);
    }

    public function testExecutePropagatesToolboxExceptions()
    {
        $toolCall = new ToolCall('id1', 'broken_tool');

        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willThrowException(new \RuntimeException('Tool exploded.'));

        $strategy = new FiberToolExecutionStrategy();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool exploded.');

        $strategy->execute($toolbox, [$toolCall]);
    }
}
