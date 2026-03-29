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
use Symfony\AI\Agent\Toolbox\ExecutionStrategy\SequentialToolExecutionStrategy;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class SequentialToolExecutionStrategyTest extends TestCase
{
    public function testExecuteReturnsEmptyArrayForNoToolCalls()
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->never())->method('execute');

        $strategy = new SequentialToolExecutionStrategy();
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

        $strategy = new SequentialToolExecutionStrategy();
        $results = $strategy->execute($toolbox, [$toolCall1, $toolCall2]);

        $this->assertCount(2, $results);
        $this->assertSame($result1, $results[0]);
        $this->assertSame($result2, $results[1]);
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

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnOnConsecutiveCalls(...$expectedResults);

        $strategy = new SequentialToolExecutionStrategy();
        $results = $strategy->execute($toolbox, $toolCalls);

        $this->assertSame($expectedResults[0], $results[0]);
        $this->assertSame($expectedResults[1], $results[1]);
        $this->assertSame($expectedResults[2], $results[2]);
    }

    public function testExecutePropagatesToolboxExceptions()
    {
        $toolCall = new ToolCall('id1', 'broken_tool');

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willThrowException(new \RuntimeException('Tool exploded.'));

        $strategy = new SequentialToolExecutionStrategy();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool exploded.');

        $strategy->execute($toolbox, [$toolCall]);
    }
}
