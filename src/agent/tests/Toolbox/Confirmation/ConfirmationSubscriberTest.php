<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Confirmation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Confirmation\AlwaysAllowPolicy;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationHandlerInterface;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationResult;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationSubscriber;
use Symfony\AI\Agent\Toolbox\Confirmation\DefaultPolicy;
use Symfony\AI\Agent\Toolbox\Confirmation\PolicyDecision;
use Symfony\AI\Agent\Toolbox\Confirmation\PolicyInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ConfirmationSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents()
    {
        $events = ConfirmationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ToolCallRequested::class, $events);
        $this->assertSame('onToolCallRequested', $events[ToolCallRequested::class]);
    }

    public function testPolicyAllowDoesNotDeny()
    {
        $handler = new class implements ConfirmationHandlerInterface {
            public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
            {
                throw new \LogicException('Should not be called');
            }
        };

        $subscriber = new ConfirmationSubscriber(new AlwaysAllowPolicy(), $handler);
        $event = $this->createEvent('my_tool');

        $subscriber->onToolCallRequested($event);

        $this->assertFalse($event->isDenied());
    }

    public function testPolicyDenyDeniesEvent()
    {
        $policy = new class implements PolicyInterface {
            public function decide(ToolCall $toolCall): PolicyDecision
            {
                return PolicyDecision::Deny;
            }
        };

        $handler = new class implements ConfirmationHandlerInterface {
            public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
            {
                throw new \LogicException('Should not be called');
            }
        };

        $subscriber = new ConfirmationSubscriber($policy, $handler);
        $event = $this->createEvent('dangerous_tool');

        $subscriber->onToolCallRequested($event);

        $this->assertTrue($event->isDenied());
        $this->assertSame('Tool execution denied by policy.', $event->getDenialReason());
    }

    public function testAskUserWithConfirmation()
    {
        $policy = new class implements PolicyInterface {
            public function decide(ToolCall $toolCall): PolicyDecision
            {
                return PolicyDecision::AskUser;
            }
        };

        $handler = new class implements ConfirmationHandlerInterface {
            public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
            {
                return ConfirmationResult::confirmed();
            }
        };

        $subscriber = new ConfirmationSubscriber($policy, $handler);
        $event = $this->createEvent('write_file');

        $subscriber->onToolCallRequested($event);

        $this->assertFalse($event->isDenied());
    }

    public function testAskUserWithDenial()
    {
        $policy = new class implements PolicyInterface {
            public function decide(ToolCall $toolCall): PolicyDecision
            {
                return PolicyDecision::AskUser;
            }
        };

        $handler = new class implements ConfirmationHandlerInterface {
            public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
            {
                return ConfirmationResult::denied();
            }
        };

        $subscriber = new ConfirmationSubscriber($policy, $handler);
        $event = $this->createEvent('write_file');

        $subscriber->onToolCallRequested($event);

        $this->assertTrue($event->isDenied());
        $this->assertSame('Tool execution denied by user.', $event->getDenialReason());
    }

    public function testRememberChoiceUpdatesDefaultPolicy()
    {
        $policy = new DefaultPolicy();

        $handler = new class implements ConfirmationHandlerInterface {
            public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
            {
                return ConfirmationResult::always();
            }
        };

        $subscriber = new ConfirmationSubscriber($policy, $handler);

        // First call should ask user (unknown tool)
        $event1 = $this->createEvent('custom_tool');
        $subscriber->onToolCallRequested($event1);

        // After remembering, policy should allow directly
        $this->assertSame(PolicyDecision::Allow, $policy->decide(new ToolCall('2', 'custom_tool')));
    }

    public function testRememberDenialUpdatesDefaultPolicy()
    {
        $policy = new DefaultPolicy();

        $handler = new class implements ConfirmationHandlerInterface {
            public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
            {
                return ConfirmationResult::never();
            }
        };

        $subscriber = new ConfirmationSubscriber($policy, $handler);

        $event = $this->createEvent('custom_tool');
        $subscriber->onToolCallRequested($event);

        // After remembering denial, policy should deny directly
        $this->assertSame(PolicyDecision::Deny, $policy->decide(new ToolCall('2', 'custom_tool')));
    }

    private function createEvent(string $toolName): ToolCallRequested
    {
        $toolCall = new ToolCall('call_123', $toolName);
        $metadata = new Tool(new ExecutionReference(self::class, '__invoke'), $toolName, 'Test tool');

        return new ToolCallRequested($toolCall, $metadata);
    }
}
