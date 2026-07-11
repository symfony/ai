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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const CACHE_PREFIX = 'stream-chat-';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CacheItemPoolInterface $cache,
        #[Autowire(service: 'ai.agent.stream')]
        private readonly AgentInterface $agent,
    ) {
    }

    public function loadMessages(): MessageBag
    {
        $item = $this->cache->getItem($this->cacheKey());

        return $item->isHit() ? $item->get() : new MessageBag();
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
        // Only answer when the latest message is still awaiting a reply. This guards against
        // reconnecting, duplicate or stale SSE connections (e.g. after a stream error or a
        // reset) that would otherwise call the model with no pending user message and trigger
        // a provider "input required" error.
        if (!$messages->isLastMessageFrom(Role::User)) {
            return Message::ofAssistant('');
        }

        $stream = $this->agent->call($messages, ['stream' => true])->getContent();
        \assert(is_iterable($stream));

        $response = '';
        foreach ($stream as $delta) {
            if ($delta instanceof TextDelta) {
                yield $response .= (string) $delta;
            }
        }

        $assistantMessage = Message::ofAssistant($response);
        $messages->add($assistantMessage);
        $this->saveMessages($messages);

        return $assistantMessage;
    }

    public function reset(): void
    {
        $this->cache->deleteItem($this->cacheKey());
    }

    private function saveMessages(MessageBag $messages): void
    {
        $item = $this->cache->getItem($this->cacheKey());
        $item->set($messages)->expiresAfter(self::CACHE_TTL);

        $this->cache->save($item);
    }

    private function cacheKey(): string
    {
        return self::CACHE_PREFIX.$this->requestStack->getSession()->getId();
    }
}
