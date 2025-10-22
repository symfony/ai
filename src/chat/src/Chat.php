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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Chat implements ChatInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly MessageStoreInterface&ManagedStoreInterface $store,
        private readonly string $name = '_chat',
    ) {
    }

    public function initiate(MessageBag $messages): void
    {
        $this->store->drop($this->name);
        $this->store->save($messages, $this->name);
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        $messages = $this->store->load($this->name);

        $messages->add($message);

        $result = $this->agent->call($messages);

        \assert($result instanceof TextResult);

        $assistantMessage = Message::ofAssistant($result->getContent());
        $assistantMessage->getMetadata()->merge($result->getMetadata());
        $messages->add($assistantMessage);

        $this->store->save($messages, $this->name);

        return $assistantMessage;
    }

    /**
     * @return \Generator<int, string, void, void>
     */
    public function stream(UserMessage $message): \Generator
    {
        $messages = $this->store->load($this->name);
        $messages->add($message);

        $result = $this->agent->call($messages, ['stream' => true]);

        \assert($result instanceof StreamResult);

        $result->addListener(new ChatStreamListener($messages, $this->store, $this->name));

        /** @var string $chunk */
        foreach ($result->getContent() as $chunk) {
            yield $chunk;
        }
    }

    public function branch(string $name, ?AgentInterface $agent = null): self
    {
        $currentMessages = $this->store->load($this->name);

        $messages = new MessageBag(...$currentMessages->getMessages());

        $this->store->save($messages, $name);

        return new self($agent ?? $this->agent, $this->store, $name);
    }
}
