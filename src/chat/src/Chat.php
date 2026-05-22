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
    ) {
    }

    public function initiate(MessageBag $messages): void
    {
        $this->store->drop();
        $this->store->save($messages);
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        // Work on a local copy so the store is never mutated before the agent call
        // succeeds. If the agent throws (e.g. InterruptedException), nothing is persisted.
        $messages = $this->store->load()->with($message);

        $result = $this->agent->call($messages);

        \assert($result instanceof TextResult);

        $assistantMessage = Message::ofAssistant($result->getContent());
        $assistantMessage->getMetadata()->merge($result->getMetadata());

        $this->store->save($messages->with($assistantMessage));

        return $assistantMessage;
    }

    public function stream(UserMessage $message): \Generator
    {
        // Same contract as submit(): work on a cloned MessageBag so the store is only
        // updated when the stream completes (via ChatStreamListener::onComplete). An
        // interrupted iteration leaves the store untouched.
        $messages = $this->store->load()->with($message);

        $result = $this->agent->call($messages, ['stream' => true]);

        \assert($result instanceof StreamResult);

        $result->addListener(new ChatStreamListener($messages, $this->store));

        yield from $result->getContent();
    }
}
