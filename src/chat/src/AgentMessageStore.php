<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat;

use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface as AgentMessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\UuidV7;

/**
 * Keeps the conversation of a chat under one fixed ID in a message store of the Agent component.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentMessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly AgentMessageStoreInterface $store,
        private readonly AbstractUid&TimeBasedUidInterface $id = new UuidV7('00000000-0000-7000-8000-000000000000'),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ($this->store instanceof ManagedMessageStoreInterface) {
            $this->store->setup($options);
        }
    }

    public function load(): MessageBag
    {
        return $this->store->load($this->id);
    }

    public function save(MessageBag $messages): void
    {
        $this->store->save($messages->withId($this->id));
    }

    public function drop(): void
    {
        $this->store->delete($this->id);
    }
}
