<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Execution\ExecutionState;
use Symfony\AI\Agent\Execution\ExecutionStatus;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class ExecutionStateTest extends TestCase
{
    public function testStatusIsPendingWhenTheUserSpokeLast()
    {
        $messages = new MessageBag(Message::forSystem('Be nice.'), Message::ofUser('Hi'));

        $this->assertSame(ExecutionStatus::Pending, ExecutionState::statusOf($messages));
    }

    public function testStatusIsPendingForAnEmptyBag()
    {
        $this->assertSame(ExecutionStatus::Pending, ExecutionState::statusOf(new MessageBag()));
    }

    public function testStatusIsCompletedWhenTheAssistantAnsweredWithoutToolCalls()
    {
        $messages = new MessageBag(Message::ofUser('Hi'), Message::ofAssistant('Hello'));

        $this->assertSame(ExecutionStatus::Completed, ExecutionState::statusOf($messages));
        $this->assertSame([], ExecutionState::pendingToolCallsOf($messages));
    }

    public function testStatusIsWaitingForInputWithAnUnansweredToolCall()
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin']);
        $messages = new MessageBag(
            Message::ofUser('Weather?'),
            Message::ofAssistant(new ToolCallResult([$toolCall])),
        );

        $this->assertSame(ExecutionStatus::WaitingForInput, ExecutionState::statusOf($messages));
        $this->assertEquals([$toolCall], ExecutionState::pendingToolCallsOf($messages));
    }

    public function testStatusIsPendingWhenTheToolCallIsAnswered()
    {
        $toolCall = new ToolCall('call_1', 'weather', ['city' => 'Berlin']);
        $messages = new MessageBag(
            Message::ofUser('Weather?'),
            Message::ofAssistant(new ToolCallResult([$toolCall])),
            Message::ofToolCall($toolCall, 'Sunny'),
        );

        $this->assertSame(ExecutionStatus::Pending, ExecutionState::statusOf($messages));
        $this->assertSame([], ExecutionState::pendingToolCallsOf($messages));
    }

    public function testOnlyTheUnansweredToolCallsArePending()
    {
        $first = new ToolCall('call_1', 'weather', []);
        $second = new ToolCall('call_2', 'time', []);
        $messages = new MessageBag(
            Message::ofUser('Hi'),
            Message::ofAssistant(new ToolCallResult([$first, $second])),
            Message::ofToolCall($first, 'Sunny'),
        );

        $this->assertSame(ExecutionStatus::WaitingForInput, ExecutionState::statusOf($messages));
        $this->assertEquals([$second], ExecutionState::pendingToolCallsOf($messages));
    }
}
