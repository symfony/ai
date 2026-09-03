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
 * Dispatched when an agent suspends execution because a tool call requires human approval.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class ToolApprovalRequestedEvent extends Event
{
    private ?ApprovalDecision $decision = null;

    /**
     * @param string[] $roles
     */
    public function __construct(
        private readonly ExecutionCheckpoint $checkpoint,
        private readonly ToolCall $toolCall,
        private readonly Tool $tool,
        private readonly ?AgentInterface $agent = null,
        private readonly ?string $prompt = null,
        private readonly array $roles = [],
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

    public function getPrompt(): ?string
    {
        return $this->prompt;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function decide(ApprovalDecision $decision): void
    {
        $this->decision = $decision;
    }

    public function hasDecision(): bool
    {
        return null !== $this->decision;
    }

    public function getDecision(): ?ApprovalDecision
    {
        return $this->decision;
    }
}
