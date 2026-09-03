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
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Strategy interface for dynamically deciding whether a tool call requires human approval.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
interface ApprovalPolicyInterface
{
    /**
     * Determines whether the given tool call needs human approval.
     */
    public function requiresApproval(Tool $tool, ToolCall $toolCall, AgentInterface $agent): bool;
}
