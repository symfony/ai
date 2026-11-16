<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Movies;

use App\Conversation\ConversationIds;
use App\Movies\Data\MovieAnswer;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Movie chat that asks the agent for a structured {@see MovieAnswer}.
 *
 * The written answer is stored as the assistant message content, while the suggested movies are kept in
 * the message metadata so the UI can render them as cards next to the text.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Chat
{
    private const CHAT = 'movies';

    public function __construct(
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.session.conversations')]
        private readonly MessageStoreInterface $messageStore,
        #[Autowire(service: 'ai.agent.movies')]
        private readonly AgentInterface $agent,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }

    public function submitMessage(string $message): void
    {
        $messages = MessageBag::forId($this->conversationIds->get(self::CHAT), $message);

        $execution = $this->agent->call($messages, ['response_format' => MovieAnswer::class]);
        $result = $execution->asObject();
        \assert($result instanceof MovieAnswer);

        // the agent stored the answer as JSON, replacing it under its ID keeps the conversation readable
        $messages = $execution->getMessages();
        $answerId = $messages->latestAs(Role::Assistant)->getId();
        $assistantMessage = Message::ofAssistant($result->answer)->withId($answerId);
        $assistantMessage->getMetadata()->add('movies', $result->movies);

        $this->messageStore->save($messages->replace($answerId, $assistantMessage));
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
    }
}
