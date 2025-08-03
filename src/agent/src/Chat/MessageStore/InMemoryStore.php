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
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryStore implements MessageStoreInterface
{
    /**
     * @var MessageBag[]
     */
    private array $messageBags;

    public function __construct(
        private readonly string $id = '_message_store_memory',
    ) {
    }

    public function save(MessageBag $messages, ?string $id = null): void
    {
        $this->messageBags[$id ?? $this->id] = $messages;
    }

    public function load(?string $id = null): MessageBag
    {
        return $this->messageBags[$id ?? $this->id] ?? new MessageBag();
    }

    public function clear(?string $id = null): void
    {
        $this->messageBags[$id ?? $this->id] = new MessageBag();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
