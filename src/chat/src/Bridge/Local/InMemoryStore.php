<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Local;

use Symfony\AI\Chat\MessageStoreIdentifierTrait;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryStore implements MessageStoreInterface
{
    use MessageStoreIdentifierTrait;

    /**
     * @var MessageBag[]
     */
    private array $messageBags;

    public function __construct(
        string $id = '_message_store_memory',
    ) {
        $this->setId($id);
    }

    public function save(MessageBag $messages, ?string $id = null): void
    {
        $this->messageBags[$id ?? $this->getId()] = $messages;
    }

    public function load(?string $id = null): MessageBag
    {
        return $this->messageBags[$id ?? $this->getId()] ?? new MessageBag();
    }

    public function clear(?string $id = null): void
    {
        $this->messageBags[$id ?? $this->getId()] = new MessageBag();
    }
}
