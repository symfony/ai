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
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

final class InMemoryStore implements MessageStoreInterface
{
    /**
     * @var MessageBagInterface[]
     */
    private array $messageBags;

    public function save(MessageBagInterface $messages): void
    {
        $this->messageBags[$messages->getId()->toRfc4122()] = $messages;
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBagInterface
    {
        return $this->messageBags[$id->toRfc4122()] ?? new MessageBag();
    }

    public function clear(): void
    {
        $this->messageBags = [];
    }
}
