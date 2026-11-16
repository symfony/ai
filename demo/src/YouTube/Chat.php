<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\YouTube;

use App\Conversation\ConversationIds;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Chat
{
    private const CHAT = 'youtube';

    public function __construct(
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.session.conversations')]
        private readonly MessageStoreInterface $messageStore,
        #[Autowire(service: 'ai.agent.youtube')]
        private readonly AgentInterface $agent,
        private readonly TranscriptFetcher $transcriptFetcher,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }

    public function start(string $videoId): void
    {
        $transcript = $this->transcriptFetcher->fetchTranscript($videoId);
        $system = <<<PROMPT
            You are an helpful assistant that answers questions about a YouTube video based on a transcript.
            If you can't answer a question, say so.

            Video ID: {$videoId}
            Transcript:
            {$transcript}
            PROMPT;

        $this->reset();
        $this->messageStore->save(MessageBag::forId(
            $this->conversationIds->get(self::CHAT),
            Message::forSystem($system),
            $videoId,
            Message::ofAssistant('What do you want to know about that video?'),
        ));
    }

    public function submitMessage(string $message): void
    {
        $messages = MessageBag::forId($this->conversationIds->get(self::CHAT), $message);

        $this->agent->call($messages)->getResult();
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
    }
}
