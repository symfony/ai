<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Reads the state of an execution from its {@see MessageBag}: the tail of the messages tells what is next.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
final class ExecutionState
{
    public static function statusOf(MessageBag $messages): ExecutionStatus
    {
        if ([] !== self::pendingToolCallsOf($messages)) {
            return ExecutionStatus::WaitingForInput;
        }

        foreach (array_reverse($messages->getMessages()) as $message) {
            if ($message instanceof SystemMessage) {
                continue;
            }

            return $message instanceof AssistantMessage ? ExecutionStatus::Completed : ExecutionStatus::Pending;
        }

        return ExecutionStatus::Pending;
    }

    /**
     * @return list<ToolCall>
     */
    public static function pendingToolCallsOf(MessageBag $messages): array
    {
        $pending = [];
        foreach ($messages->getMessages() as $message) {
            if ($message instanceof AssistantMessage) {
                foreach ($message->getToolCalls() as $toolCall) {
                    $pending[$toolCall->getId()] = $toolCall;
                }
            }

            if ($message instanceof ToolCallMessage) {
                unset($pending[$message->getToolCall()->getId()]);
            }
        }

        return array_values($pending);
    }
}
