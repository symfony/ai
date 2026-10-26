<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Stream;

use App\Conversation\ConversationIds;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Chat
{
    private const CHAT = 'stream';

    public function __construct(
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.cache.shared_conversations')]
        private readonly MessageStoreInterface $messageStore,
        #[Autowire(service: 'ai.agent.stream')]
        private readonly AgentInterface $agent,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }

    public function submitMessage(string $message): UserMessage
    {
        $userMessage = Message::ofUser($message);

        $this->messageStore->save($this->loadMessages()->with($userMessage));

        return $userMessage;
    }

    /**
     * @return \Generator<int, string, void, AssistantMessage>
     */
    public function getAssistantResponse(MessageBag $messages): \Generator
    {
        // Only answer when the latest message is still awaiting a reply. This guards against
        // reconnecting, duplicate or stale SSE connections (e.g. after a stream error or a
        // reset) that would otherwise call the model with no pending user message and trigger
        // a provider "input required" error.
        if (!$messages->isLastMessageFrom(Role::User)) {
            return Message::ofAssistant('');
        }

        $execution = $this->agent->call($messages, ['stream' => true]);

        $response = '';
        foreach ($execution->asTextStream() as $delta) {
            yield $response .= $delta;
        }

        return $execution->getMessages()->latestAs(Role::Assistant);
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
    }
}
