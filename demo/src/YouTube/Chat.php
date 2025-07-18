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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const SESSION_KEY = 'youtube-chat';

    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'ai.agent.youtube')]
        private readonly AgentInterface $agent,
        private readonly TranscriptFetcher $transcriptFetcher,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, new MessageBag());
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

        $messages = new MessageBag(
            Message::forSystem($system),
            Message::ofAssistant('What do you want to know about that video?'),
        );

        $this->reset();
        $this->saveMessages($messages);
    }

    public function submitMessage(string $message): void
    {
        $messages = $this->loadMessages();

        $messages->add(Message::ofUser($message));
        $result = $this->agent->call($messages);

        \assert($result instanceof TextResult);

        $messages->add(Message::ofAssistant($result->getContent()));

        $this->saveMessages($messages);
    }

    public function reset(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    private function saveMessages(MessageBag $messages): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $messages);
    }
}
