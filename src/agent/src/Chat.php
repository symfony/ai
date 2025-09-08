<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Chat implements ChatInterface
{
    private string $id;

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly MessageStoreInterface $store,
    ) {
        $this->id = $this->store->getId();
    }

    public function initiate(MessageBag $messages, ?string $id = null): void
    {
        $this->id = $id ?? $this->id;

        $this->store->clear();
        $this->store->save($messages, $this->id);
    }

    public function submit(UserMessage $message, ?string $id = null): AssistantMessage
    {
        $messages = $this->store->load($id ?? $this->id);

        $messages->add($message);
        $result = $this->agent->call($messages);

        \assert($result instanceof TextResult);

        $assistantMessage = Message::ofAssistant($result->getContent());
        $messages->add($assistantMessage);

        $this->store->save($messages, $this->id);

        return $assistantMessage;
    }

    public function getCurrentMessageBag(): MessageBag
    {
        return $this->store->load($this->id);
    }

    public function getMessageBag(string $id): MessageBag
    {
        return $this->store->load($id);
    }

    public function getId(): string
    {
        return $this->id;
    }
}
