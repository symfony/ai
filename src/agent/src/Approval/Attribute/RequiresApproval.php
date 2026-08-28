<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval\Attribute;

use Symfony\AI\Agent\Approval\ApprovalPolicyInterface;

/**
 * Marks a tool or tool method as requiring human approval before execution.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RequiresApproval
{
    /**
     * @param string|null                                $prompt   A human-readable prompt or template explaining what requires approval
     * @param string[]                                   $roles    Security roles authorized to approve this tool execution
     * @param class-string<ApprovalPolicyInterface>|null $policy   Custom policy class to evaluate whether approval is needed
     * @param array<string, mixed>                       $metadata Arbitrary custom data attached to the approval requirement
     */
    public function __construct(
        public readonly ?string $prompt = null,
        public readonly array $roles = [],
        public readonly ?string $policy = null,
        public readonly array $metadata = [],
    ) {
    }
}
