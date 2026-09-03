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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Approval\Attribute\RequiresApproval;
use Symfony\AI\Agent\Approval\Checkpoint\CheckpointSignerInterface;
use Symfony\AI\Agent\Approval\Checkpoint\CheckpointStoreInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Manages tool approval evaluations, policies, checkpoint stores and signers.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
interface ApprovalManagerInterface
{
    /**
     * Determines whether a specific tool call requires human approval.
     */
    public function requiresApproval(Tool $tool, ToolCall $toolCall, ?AgentInterface $agent = null): bool;

    /**
     * Extracts the RequiresApproval attribute metadata from a tool definition, if present.
     */
    public function getApprovalRequirement(Tool $tool): ?RequiresApproval;

    /**
     * Formats the human-readable approval prompt for the tool call.
     */
    public function formatPrompt(Tool $tool, ToolCall $toolCall): ?string;

    /**
     * Returns the configured checkpoint store, if any.
     */
    public function getCheckpointStore(): ?CheckpointStoreInterface;

    /**
     * Returns the configured checkpoint signer, if any.
     */
    public function getSigner(): ?CheckpointSignerInterface;
}
