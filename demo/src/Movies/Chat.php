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

use App\Movies\Data\MovieAnswer;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Session-backed movie chat that asks the agent for a structured {@see MovieAnswer}.
 *
 * The written answer is stored as the assistant message content, while the suggested movies are kept in
 * the message metadata so the UI can render them as cards next to the text.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Chat
{
    private const SESSION_KEY = 'movies-chat';

    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'ai.agent.movies')]
        private readonly AgentInterface $agent,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, new MessageBag());
    }

    public function submitMessage(string $message): void
    {
        $messages = $this->loadMessages();
        $messages->add(Message::ofUser($message));

        $result = $this->agent->call($messages, ['response_format' => MovieAnswer::class])->asObject();
        \assert($result instanceof MovieAnswer);

        $assistantMessage = Message::ofAssistant($result->answer);
        $assistantMessage->getMetadata()->add('movies', $result->movies);
        $messages->add($assistantMessage);

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
