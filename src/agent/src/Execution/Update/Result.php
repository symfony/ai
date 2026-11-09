<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution\Update;

use Symfony\AI\Agent\Execution\ExecutionStatus;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Execution\UpdateType;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * The terminal update of an agent execution, wrapping the final platform result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Result implements UpdateInterface
{
    /**
     * @param (\Closure(?ResultInterface=): ?MessageBag)|MessageBag|null $messages         built lazily, since not every result maps to an assistant message, the closure gets the result replacing the answer if any
     * @param ExecutionStatus|null                                       $status           set when a stepped run stopped before its final answer, then {@see ExecutionStatus::Pending} or {@see ExecutionStatus::WaitingForInput}
     * @param list<ToolCall>                                             $pendingToolCalls the calls a stopped run waits for
     */
    public function __construct(
        private readonly ResultInterface $result,
        private \Closure|MessageBag|null $messages = null,
        private readonly ?ExecutionStatus $status = null,
        private readonly array $pendingToolCalls = [],
    ) {
    }

    /**
     * The status of a run that stopped before its final answer, null for a run that finished.
     */
    public function getStatus(): ?ExecutionStatus
    {
        return $this->status;
    }

    /**
     * @return list<ToolCall>
     */
    public function getPendingToolCalls(): array
    {
        return $this->pendingToolCalls;
    }

    public function getType(): UpdateType
    {
        return UpdateType::Result;
    }

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    /**
     * The messages of the execution, ending with the final answer, null if the agent doesn't provide them.
     *
     * @param ResultInterface|null $answer replaces the result as final answer, e.g. after output processing
     */
    public function getMessages(?ResultInterface $answer = null): ?MessageBag
    {
        if (null !== $answer && $this->messages instanceof \Closure) {
            return ($this->messages)($answer);
        }

        if ($this->messages instanceof \Closure) {
            $this->messages = ($this->messages)();
        }

        return $this->messages;
    }
}
