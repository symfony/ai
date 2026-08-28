<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval\Event;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Approval\ApprovalDecision;
use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpoint;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a pending tool approval decision is provided and execution resumes.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class ToolApprovalResolvedEvent extends Event
{
    public function __construct(
        private readonly ExecutionCheckpoint $checkpoint,
        private readonly ToolCall $toolCall,
        private readonly Tool $tool,
        private readonly ?AgentInterface $agent = null,
        private readonly ?ApprovalDecision $decision = null,
    ) {
    }

    public function getCheckpoint(): ExecutionCheckpoint
    {
        return $this->checkpoint;
    }

    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }

    public function getTool(): Tool
    {
        return $this->tool;
    }

    public function getAgent(): ?AgentInterface
    {
        return $this->agent;
    }

    public function getDecision(): ?ApprovalDecision
    {
        return $this->decision;
    }
}
