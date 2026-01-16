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
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\MockResponse;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class ChatTest extends TestCase
{
    public function testItInitiatesChatByClearingAndSavingMessages()
    {
        $store = new InMemoryStore();

        $chat = new Chat(new MockAgent(), $store);
        $chat->initiate(new MessageBag());

        $this->assertCount(0, $store->load());
    }

    public function testItSubmitsUserMessageAndReturnsAssistantMessage()
    {
        $store = new InMemoryStore();

        $chat = new Chat(new MockAgent([
            'Hello, how are you?' => new MockResponse('I am doing well, thank you!'),
        ]), $store);

        $result = $chat->submit(Message::ofUser('Hello, how are you?'));

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame('I am doing well, thank you!', $result->getContent());
        $this->assertCount(2, $store->load('_chat'));
    }

    public function testItAppendsMessagesToExistingConversation()
    {
        $store = new InMemoryStore();

        $existingUserMessage = Message::ofUser('What is the weather?');
        $existingAssistantMessage = Message::ofAssistant('I cannot provide weather information.');

        $existingMessages = new MessageBag();
        $existingMessages->add($existingUserMessage);
        $existingMessages->add($existingAssistantMessage);

        $chat = new Chat(new MockAgent([
            'Can you help with programming?' => new MockResponse('Yes, I can help with programming!'),
        ]), $store);

        $result = $chat->submit(Message::ofUser('Can you help with programming?'));

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame('Yes, I can help with programming!', $result->getContent());
        $this->assertCount(2, $store->load('_chat'));
    }

    public function testItHandlesEmptyMessageStore()
    {
        $store = new InMemoryStore();

        $chat = new Chat(new MockAgent([
            'First message' => new MockResponse('First response'),
        ]), $store);

        $result = $chat->submit(Message::ofUser('First message'));

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame('First response', $result->getContent());
        $this->assertCount(2, $store->load('_chat'));
    }

    public function testItCanBeForked()
    {
        $store = new InMemoryStore();

        $chat = new Chat(new MockAgent([
            'hello world' => new MockResponse('Hello there'),
            'Second hello world' => new MockResponse('Hello there'),
        ]), $store);

        $chat->submit(Message::ofUser('hello world'));

        $this->assertCount(2, $store->load('_chat'));

        $newChat = $chat->branch('foo');

        $newChat->submit(Message::ofUser('Second hello world'));

        $this->assertCount(4, $store->load('foo'));
    }
}
