<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Wikipedia;

use App\Conversation\ConversationIds;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class Chat
{
    private const CHAT = 'wikipedia';
    private const TOPIC_SESSION_KEY = 'wikipedia-topic';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.cache.shared_conversations')]
        private readonly MessageStoreInterface $messageStore,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Stays the same after a reset, since the browser keeps its subscription to the topic.
     */
    public function getTopic(): string
    {
        $session = $this->requestStack->getSession();

        if (!$session->has(self::TOPIC_SESSION_KEY)) {
            $session->set(self::TOPIC_SESSION_KEY, 'https://symfony.com/ai/demo/wikipedia/'.Uuid::v4()->toRfc4122());
        }

        return $session->get(self::TOPIC_SESSION_KEY);
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }

    public function isResearching(): bool
    {
        return $this->loadMessages()->isLastMessageFrom(Role::User);
    }

    public function submitMessage(string $message): void
    {
        $messages = $this->loadMessages()->with(Message::ofUser($message));

        $this->messageStore->save($messages);
        $this->bus->dispatch(new Research($messages->getId()->toRfc4122(), $this->getTopic()));
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
    }
}
