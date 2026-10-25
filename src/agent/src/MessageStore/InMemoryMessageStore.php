<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MessageStore;

use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryMessageStore implements MessageStoreInterface
{
    /**
     * @var array<string, MessageBag>
     */
    private array $messages = [];

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        return $this->messages[$id->toRfc4122()] ?? MessageBag::forId($id);
    }

    public function save(MessageBag $messages): void
    {
        $id = $messages->getId()->toRfc4122();
        $stored = isset($this->messages[$id]) ? $this->messages[$id]->getMessages() : [];

        if (array_map(self::id(...), $stored) !== array_map(self::id(...), \array_slice($messages->getMessages(), 0, \count($stored)))) {
            throw ConflictException::forMessages($messages);
        }

        $this->messages[$id] = $messages;
    }

    private static function id(MessageInterface $message): string
    {
        return $message->getId()->toRfc4122();
    }
}
