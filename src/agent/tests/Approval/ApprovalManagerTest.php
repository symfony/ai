<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Approval;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Approval\ApprovalManager;
use Symfony\AI\Agent\Approval\ApprovalPolicyInterface;
use Symfony\AI\Agent\Approval\Attribute\RequiresApproval;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ApprovalManagerTest extends TestCase
{
    public function testRequiresApprovalFromAttribute()
    {
        $tool = new Tool(
            new ExecutionReference('App\\Service', 'action'),
            'sensitive_action',
            'Performs a sensitive action',
            null,
            ['requires_approval' => new RequiresApproval(prompt: 'Confirm action with ID #{id}')],
        );

        $toolCall = new ToolCall('call_1', 'sensitive_action', ['id' => 42]);
        $agent = $this->createStub(AgentInterface::class);

        $manager = new ApprovalManager();
        $this->assertTrue($manager->requiresApproval($tool, $toolCall, $agent));
        $this->assertSame('Confirm action with ID 42', $manager->formatPrompt($tool, $toolCall));
    }

    public function testRequiresApprovalFromDynamicPolicy()
    {
        $policy = new class implements ApprovalPolicyInterface {
            public function requiresApproval(Tool $tool, ToolCall $toolCall, AgentInterface $agent): bool
            {
                $amount = (float) ($toolCall->getArguments()['amount'] ?? 0);

                return $amount > 100.0;
            }
        };

        $tool = new Tool(
            new ExecutionReference('App\\PaymentService', 'pay'),
            'pay',
            'Process payment',
        );

        $smallPayment = new ToolCall('call_1', 'pay', ['amount' => 50.0]);
        $largePayment = new ToolCall('call_2', 'pay', ['amount' => 500.0]);
        $agent = $this->createStub(AgentInterface::class);

        $manager = new ApprovalManager([$policy]);
        $this->assertFalse($manager->requiresApproval($tool, $smallPayment, $agent));
        $this->assertTrue($manager->requiresApproval($tool, $largePayment, $agent));
    }
}
