<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Wikipedia;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class Chat
{
    private const SESSION_KEY = 'wikipedia-chat-id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Conversations $conversations,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function getId(): string
    {
        $session = $this->requestStack->getSession();

        if (!$session->has(self::SESSION_KEY)) {
            $session->set(self::SESSION_KEY, Uuid::v4()->toRfc4122());
        }

        return $session->get(self::SESSION_KEY);
    }

    public function getTopic(): string
    {
        return Conversations::topic($this->getId());
    }

    public function loadMessages(): MessageBag
    {
        return $this->conversations->load($this->getId());
    }

    public function isResearching(): bool
    {
        return $this->loadMessages()->isLastMessageFrom(Role::User);
    }

    public function submitMessage(string $message): void
    {
        $this->conversations->save($this->getId(), $this->loadMessages()->with(Message::ofUser($message)));
        $this->bus->dispatch(new Research($this->getId()));
    }

    public function reset(): void
    {
        $this->conversations->delete($this->getId());
    }
}
