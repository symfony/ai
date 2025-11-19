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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class MessageBag implements \Countable
{
    use MetadataAwareTrait;

    /**
     * @var list<MessageInterface>
     */
    private array $messages;

    public function __construct(MessageInterface ...$messages)
    {
        $this->messages = array_values($messages);
    }

    public function add(MessageInterface $message): void
    {
        $this->messages[] = $message;
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

    public function with(MessageInterface $message): self
    {
        $messages = clone $this;
        $messages->add($message);

        return $messages;
    }

    public function merge(self $messageBag): self
    {
        $messages = clone $this;
        $messages->messages = array_merge($messages->messages, $messageBag->getMessages());

        return $messages;
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

    public function prepend(MessageInterface $message): self
    {
        $messages = clone $this;
        $messages->messages = array_merge([$message], $messages->messages);

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
}
