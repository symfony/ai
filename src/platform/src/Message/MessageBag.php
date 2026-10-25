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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements \IteratorAggregate<int, MessageInterface>
 */
class MessageBag implements \Countable, \IteratorAggregate
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
        if (null !== $this->metadata) {
            $this->metadata = clone $this->metadata;
        }
    }

    public function getId(): AbstractUid&TimeBasedUidInterface
    {
        return $this->id;
    }

    public function withId(AbstractUid&TimeBasedUidInterface $id): static
    {
        $messages = clone $this;
        $messages->id = $id;

        return $messages;
    }

    /**
     * @return list<MessageInterface>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getSystemMessage(string $separator = \PHP_EOL.\PHP_EOL): ?SystemMessage
    {
        $systemMessages = [];
        foreach ($this->messages as $message) {
            if ($message instanceof SystemMessage) {
                $systemMessages[] = $message;
            }
        }

        if ([] === $systemMessages) {
            return null;
        }

        if (1 === \count($systemMessages)) {
            return $systemMessages[0];
        }

        $content = implode($separator, array_map(
            static fn (SystemMessage $message): string => (string) $message->getContent(),
            $systemMessages,
        ));

        return new SystemMessage($content);
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
        $messages->messages[] = $message;

        return $messages;
    }

    public function merge(self $messageBag): self
    {
        $messages = clone $this;
        $messages->messages = array_merge($messages->messages, $messageBag->getMessages());

        return $messages;
    }

    public function replace(AbstractUid&TimeBasedUidInterface $uuid, MessageInterface $newMessage): self
    {
        $position = null;
        foreach ($this->messages as $index => $message) {
            if (!$message->getId()->equals($uuid)) {
                continue;
            }

            if (null !== $position) {
                throw new InvalidArgumentException(\sprintf('More than one message found for Uuid: "%s".', $uuid->toRfc4122()));
            }

            $position = $index;
        }

        if (null === $position) {
            throw new InvalidArgumentException(\sprintf('No message found for Uuid: "%s".', $uuid->toRfc4122()));
        }

        $messages = clone $this;
        $messages->messages[$position] = $newMessage;

        return $messages;
    }

    public function withoutSystemMessage(): self
    {
        $messages = clone $this;
        $messages->messages = [];
        foreach ($this->messages as $message) {
            if (!$message instanceof SystemMessage) {
                $messages->messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Clones the MessageBag without tool-call messages and tool-call-only assistant messages,
     * leaving only the conversational messages (user input and assistant messages carrying text).
     */
    public function withoutToolMessages(): self
    {
        $messages = clone $this;
        $messages->messages = [];
        foreach ($this->messages as $message) {
            if ($message instanceof ToolCallMessage) {
                continue;
            }

            if ($message instanceof AssistantMessage && null === $message->asText()) {
                continue;
            }

            $messages->messages[] = $message;
        }

        return $messages;
    }

    /**
     * Clones the MessageBag without previous system message and prepends the given one.
     */
    public function withSystemMessage(SystemMessage $message): self
    {
        $messages = $this->withoutSystemMessage();
        array_unshift($messages->messages, $message);

        return $messages;
    }

    /**
     * @return ($role is Role::User ? UserMessage : ($role is Role::Assistant ? AssistantMessage : ($role is Role::System ? SystemMessage : ToolCallMessage)))
     *
     * @throws InvalidArgumentException
     */
    public function latestAs(Role $role): MessageInterface
    {
        $class = match ($role) {
            Role::User => UserMessage::class,
            Role::Assistant => AssistantMessage::class,
            Role::System => SystemMessage::class,
            Role::ToolCall => ToolCallMessage::class,
        };

        for ($index = \count($this->messages) - 1; $index >= 0; --$index) {
            if ($this->messages[$index] instanceof $class) {
                return $this->messages[$index];
            }
        }

        throw new InvalidArgumentException(\sprintf('No message found for role "%s".', $role->name));
    }

    public function isLastMessageFrom(Role $role): bool
    {
        if ([] === $this->messages) {
            return false;
        }

        return $this->messages[\count($this->messages) - 1]->getRole() === $role;
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

    /**
     * @return \ArrayIterator<int, MessageInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->messages);
    }
}
