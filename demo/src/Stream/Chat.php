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

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const SESSION_KEY = 'stream-chat';

    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'ai.agent.stream')]
        private readonly AgentInterface $agent,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, new MessageBag());
    }

    public function submitMessage(string $message): UserMessage
    {
        $messages = $this->loadMessages();

        $userMessage = Message::ofUser($message);
        $messages->add($userMessage);

        $this->saveMessages($messages);

        return $userMessage;
    }

    /**
     * @return \Generator<int, string, void, AssistantMessage>
     */
    public function getAssistantResponse(MessageBag $messages): \Generator
    {
        $stream = $this->agent->call($messages, ['stream' => true])->getContent();
        \assert(is_iterable($stream));

        $response = '';
        foreach ($stream as $chunk) {
            yield $response .= $chunk;
        }

        $assistantMessage = Message::ofAssistant($response);
        $messages->add($assistantMessage);
        $this->saveMessages($messages);

        return $assistantMessage;
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
