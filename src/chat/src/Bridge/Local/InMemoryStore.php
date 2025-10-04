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

use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryStore implements ManagedStoreInterface, MessageStoreInterface
{
    private MessageBag $messages;

    public function setup(array $options = []): void
    {
        $this->messages = new MessageBag();
    }

    public function save(MessageBag $messages): void
    {
        $this->messages = $messages;
    }

    public function load(): MessageBag
    {
        return $this->messages ?? new MessageBag();
    }

    public function drop(): void
    {
        $this->messages = new MessageBag();
    }
}
