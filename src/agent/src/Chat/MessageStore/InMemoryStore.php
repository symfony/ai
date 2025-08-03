<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Chat\MessageStore;

use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Agent\Chat\SessionAwareMessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

final class InMemoryStore implements MessageStoreInterface, SessionAwareMessageStoreInterface
{
    /**
     * @var MessageInterface[]
     */
    private array $messages;
    private (AbstractUid&TimeBasedUidInterface)|null $session = null;

    public function save(MessageBagInterface $messages): void
    {
        if (null === $this->session) {
            $this->messages = $messages->getMessages();

            return;
        }

        $this->messages[$this->session->toRfc4122()] = $messages;
    }

    public function load(): MessageBagInterface
    {
        if (null === $this->session) {
            return new MessageBag(...$this->messages);
        }

        return new MessageBag($this->messages[$this->session->toRfc4122()]);
    }

    public function clear(): void
    {
        if (null === $this->session) {
            $this->messages = [];

            return;
        }

        $this->messages[$this->session->toRfc4122()] = new MessageBag();
    }

    public function withSession(AbstractUid&TimeBasedUidInterface $session): MessageStoreInterface&SessionAwareMessageStoreInterface
    {
        $store = clone $this;
        $store->session = $session;

        return $store;
    }
}
