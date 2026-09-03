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
 * Default implementation of ApprovalManagerInterface.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class ApprovalManager implements ApprovalManagerInterface
{
    /**
     * @param iterable<ApprovalPolicyInterface> $policies
     */
    public function __construct(
        private readonly iterable $policies = [],
        private readonly ?CheckpointStoreInterface $checkpointStore = null,
        private readonly ?CheckpointSignerInterface $signer = null,
    ) {
    }

    public function requiresApproval(Tool $tool, ToolCall $toolCall, ?AgentInterface $agent = null): bool
    {
        $requirement = $this->getApprovalRequirement($tool);

        if (null !== $requirement) {
            if (null !== $requirement->policy) {
                foreach ($this->policies as $policy) {
                    if ($policy instanceof $requirement->policy) {
                        return $policy->requiresApproval($tool, $toolCall, $agent);
                    }
                }
            }

            foreach ($this->policies as $policy) {
                if ($policy->requiresApproval($tool, $toolCall, $agent)) {
                    return true;
                }
            }

            return true;
        }

        foreach ($this->policies as $policy) {
            if ($policy->requiresApproval($tool, $toolCall, $agent)) {
                return true;
            }
        }

        return false;
    }

    public function getApprovalRequirement(Tool $tool): ?RequiresApproval
    {
        $requirement = $tool->getMetadataValue('requires_approval');

        return $requirement instanceof RequiresApproval ? $requirement : null;
    }

    public function formatPrompt(Tool $tool, ToolCall $toolCall): ?string
    {
        $requirement = $this->getApprovalRequirement($tool);
        if (null === $requirement || null === $requirement->prompt) {
            return null;
        }

        $prompt = $requirement->prompt;
        $arguments = $toolCall->getArguments();

        foreach ($arguments as $key => $value) {
            $stringVal = \is_scalar($value) ? (string) $value : json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            $prompt = str_replace('#{'.$key.'}', (string) $stringVal, $prompt);
            $prompt = str_replace('${'.$key.'}', (string) $stringVal, $prompt);
            $prompt = str_replace('{'.$key.'}', (string) $stringVal, $prompt);
        }

        return $prompt;
    }

    public function getCheckpointStore(): ?CheckpointStoreInterface
    {
        return $this->checkpointStore;
    }

    public function getSigner(): ?CheckpointSignerInterface
    {
        return $this->signer;
    }
}
