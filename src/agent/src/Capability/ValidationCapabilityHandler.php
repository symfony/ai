<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Capability;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\ValidationFailedException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ValidationCapabilityHandler implements CapabilityHandlerInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param ValidationGroupInputCapability|ValidationGroupOutputCapability $capability
     */
    public function handle(AgentInterface $agent, MessageBag $messages, array $options, InputCapabilityInterface|OutputCapabilityInterface $capability): void
    {
        $message = match (true) {
            $capability instanceof ValidationGroupInputCapability => $messages->getUserMessage(),
            $capability instanceof ValidationGroupOutputCapability => $messages->getAssistantMessage(),
            default => throw new InvalidArgumentException(\sprintf('The "%s" capability handler requires either a "%s" or a "%s".', self::class, ValidationGroupInputCapability::class, ValidationGroupOutputCapability::class)),
        };

        if (!$message instanceof MessageInterface) {
            throw new InvalidArgumentException(\sprintf('The "%s" capability handler requires either a user message or an assistant message.', self::class));
        }

        $violations = $this->validator->validate($message->getContent(), groups: $capability->getGroups());
        if (0 === \count($violations)) {
            return;
        }

        throw new ValidationFailedException($message, $violations);
    }

    public function support(InputCapabilityInterface|OutputCapabilityInterface $capability): bool
    {
        return $capability instanceof ValidationGroupInputCapability || $capability instanceof ValidationGroupOutputCapability;
    }
}
