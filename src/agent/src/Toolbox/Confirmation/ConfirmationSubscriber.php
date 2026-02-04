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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        private readonly LoggerInterface $logger = new NullLogger(),
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
            $this->logger->debug(\sprintf('Tool "%s" denied by policy.', $toolCall->getName()));
            $event->deny('Tool execution denied by policy.');

            return;
        }

        if (PolicyDecision::Allow === $decision) {
            $this->logger->debug(\sprintf('Tool "%s" allowed by policy.', $toolCall->getName()));

            return;
        }

        // PolicyDecision::AskUser
        $this->logger->debug(\sprintf('Requesting user confirmation for tool "%s".', $toolCall->getName()));
        $result = $this->confirmationHandler->requestConfirmation($toolCall);

        if ($result->shouldRemember() && $this->policy instanceof RememberablePolicyInterface) {
            $rememberedDecision = $result->isConfirmed() ? PolicyDecision::Allow : PolicyDecision::Deny;
            $this->policy->remember($toolCall->getName(), $rememberedDecision);
            $this->logger->debug(\sprintf('Remembered decision "%s" for tool "%s".', $rememberedDecision->value, $toolCall->getName()));
        }

        if (!$result->isConfirmed()) {
            $this->logger->debug(\sprintf('Tool "%s" denied by user.', $toolCall->getName()));
            $event->deny('Tool execution denied by user.');
        }
    }
}
