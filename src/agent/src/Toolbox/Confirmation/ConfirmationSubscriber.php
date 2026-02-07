<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Confirmation;

use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConfirmationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PolicyInterface $policy,
        private readonly ConfirmationHandlerInterface $confirmationHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ToolCallRequested::class => 'onToolCallRequested',
        ];
    }

    public function onToolCallRequested(ToolCallRequested $event): void
    {
        $toolCall = $event->getToolCall();
        $decision = $this->policy->decide($toolCall);

        if (PolicyDecision::Deny === $decision) {
            $event->deny('Tool execution denied by policy.');

            return;
        }

        if (PolicyDecision::Allow === $decision) {
            return;
        }

        // PolicyDecision::AskUser
        $result = $this->confirmationHandler->requestConfirmation($toolCall);

        if ($result->shouldRemember() && $this->policy instanceof DefaultPolicy) {
            $this->policy->remember(
                $toolCall->getName(),
                $result->isConfirmed() ? PolicyDecision::Allow : PolicyDecision::Deny
            );
        }

        if (!$result->isConfirmed()) {
            $event->deny('Tool execution denied by user.');
        }
    }
}
