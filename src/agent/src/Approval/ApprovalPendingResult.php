<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval;

use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpoint;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Result returned when an agent suspends execution because a tool call requires human confirmation.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class ApprovalPendingResult extends BaseResult
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private readonly ExecutionCheckpoint $checkpoint,
        private readonly ToolCall $toolCall,
        private readonly Tool $tool,
        private readonly ?string $token = null,
        private readonly ?string $prompt = null,
        private readonly array $roles = [],
    ) {
    }

    public function getContent(): string
    {
        return $this->prompt ?? \sprintf('Approval required for tool "%s" with arguments: %s', $this->toolCall->getName(), json_encode($this->toolCall->getArguments(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
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

    public function getToken(): ?string
    {
        return $this->token;
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
}
