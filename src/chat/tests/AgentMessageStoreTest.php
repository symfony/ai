<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Chat\AgentMessageStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\Component\Uid\Uuid;

final class AgentMessageStoreTest extends TestCase
{
    public function testChatKeepsItsConversationInTheAgentMessageStore()
    {
        $id = Uuid::v7();
        $store = new InMemoryMessageStore();
        $chat = new Chat(new MockAgent(['Hello' => 'Hi', 'Thanks' => 'You are welcome']), new AgentMessageStore($store, $id));

        $chat->initiate(new MessageBag(Message::forSystem('You are a helpful assistant.')));
        $chat->submit(Message::ofUser('Hello'));
        $chat->submit(Message::ofUser('Thanks'));

        $messages = $store->load($id);
        $this->assertCount(5, $messages);
        $answer = $messages->latestAs(Role::Assistant);
        $this->assertInstanceOf(AssistantMessage::class, $answer);
        $this->assertSame('You are welcome', $answer->asText());
    }

    public function testInitiatingDropsThePreviousConversation()
    {
        $store = new InMemoryMessageStore();
        $chat = new Chat(new MockAgent(['Hello' => 'Hi']), $messageStore = new AgentMessageStore($store));
        $chat->submit(Message::ofUser('Hello'));

        $chat->initiate(new MessageBag(Message::forSystem('You are a helpful assistant.')));

        $this->assertCount(1, $messageStore->load());
    }

    public function testInitiatingKeepsOtherConversationsOfTheStore()
    {
        $store = new InMemoryMessageStore();
        $other = new MessageBag(Message::ofUser('Bonjour'));
        $store->save($other);
        $chat = new Chat(new MockAgent(['Hello' => 'Hi']), new AgentMessageStore($store));

        $chat->initiate(new MessageBag(Message::forSystem('You are a helpful assistant.')));

        $this->assertCount(1, $store->load($other->getId()));
    }
}
