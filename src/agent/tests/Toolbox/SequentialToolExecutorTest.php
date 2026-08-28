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
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

final class SequentialToolExecutorTest extends TestCase
{
    public function testItExecutesTheToolCallsInOrder()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $executed = [];
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(static function (ToolCall $toolCall) use (&$executed): ToolResult {
                $executed[] = $toolCall->getName();

                return new ToolResult($toolCall, 'Result of '.$toolCall->getName());
            });

        $executor = new SequentialToolExecutor($toolbox);
        $generator = $executor->execute([$toolCall1, $toolCall2]);
        iterator_to_array($generator);
        $results = $generator->getReturn();

        $this->assertSame(['tool1', 'tool2'], $executed);
        $this->assertCount(2, $results);
        $this->assertSame($toolCall1, $results[0]->getToolCall());
        $this->assertSame($toolCall2, $results[1]->getToolCall());
    }

    public function testItReportsAProgressUpdatePerToolCall()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('execute')
            ->willReturnCallback(static fn (ToolCall $toolCall): ToolResult => new ToolResult($toolCall, 'Result of '.$toolCall->getName()));

        $executor = new SequentialToolExecutor($toolbox);
        $updates = iterator_to_array($executor->execute([$toolCall1, $toolCall2]));

        $this->assertCount(2, $updates);
        $this->assertContainsOnlyInstancesOf(Progress::class, $updates);
        $this->assertSame('tool_call', $updates[0]->getStage());
        $this->assertSame($toolCall1, $updates[0]->getPayload());
        $this->assertSame($toolCall2, $updates[1]->getPayload());
    }

    public function testItReturnsAnEmptyListWhenThereAreNoToolCalls()
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->never())->method('execute');

        $executor = new SequentialToolExecutor($toolbox);
        $generator = $executor->execute([]);
        iterator_to_array($generator);

        $this->assertSame([], $generator->getReturn());
    }
}
