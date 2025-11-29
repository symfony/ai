<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message;

use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements \IteratorAggregate<MessageInterface>
 */
class MessageBag implements \IteratorAggregate, \Countable
{
    use MetadataAwareTrait;

    private AbstractUid&TimeBasedUidInterface $id;

    /**
     * @var list<MessageInterface>
     */
    private array $messages;

    public function __construct(MessageInterface ...$messages)
    {
        $this->messages = array_values($messages);
        $this->id = Uuid::v7();
    }

    public function __clone()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): AbstractUid&TimeBasedUidInterface
    {
        return $this->id;
    }

    public function add(MessageInterface $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    public function prepend(MessageInterface $message): self
    {
        $this->messages = array_merge([$message], $this->messages);

        return $this;
    }

    /**
     * @return list<MessageInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getSystemMessage(): ?SystemMessage
    {
        foreach ($this->messages as $message) {
            if ($message instanceof SystemMessage) {
                return $message;
            }
        }

        return null;
    }

    public function getUserMessage(): ?UserMessage
    {
        foreach ($this->messages as $message) {
            if ($message instanceof UserMessage) {
                return $message;
            }
        }

        return null;
    }

    public function merge(self $messageBag): self
    {
        $this->messages = array_merge($this->messages, $messageBag->getMessages());

        return $this;
    }

    public function withoutSystemMessage(): self
    {
        $messages = clone $this;
        $messages->messages = array_values(array_filter(
            $messages->messages,
            static fn (MessageInterface $message) => !$message instanceof SystemMessage,
        ));

        return $messages;
    }

    public function containsAudio(): bool
    {
        foreach ($this->messages as $message) {
            if ($message instanceof UserMessage && $message->hasAudioContent()) {
                return true;
            }
        }

        return false;
    }

    public function containsImage(): bool
    {
        foreach ($this->messages as $message) {
            if ($message instanceof UserMessage && $message->hasImageContent()) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return \count($this->messages);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->messages);
    }
}
