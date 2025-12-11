<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\InMemory;

use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store implements ManagedStoreInterface, MessageStoreInterface
{
    /**
     * @var MessageBag[]
     */
    private array $messages = [];

    public function __construct(
        private readonly string $identifier = '_message_store_memory',
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->messages[$this->identifier] = new MessageBag();
    }

    public function save(MessageBag $messages): void
    {
        $this->messages[$this->identifier] = $messages;
    }

    public function load(): MessageBag
    {
        return $this->messages[$this->identifier] ?? new MessageBag();
    }

    public function drop(): void
    {
        $this->messages[$this->identifier] = new MessageBag();
    }
}
