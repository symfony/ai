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
    private MockAgent $agent;
    private InMemoryStore $store;
    private Chat $chat;

    protected function setUp(): void
    {
        $this->agent = new MockAgent();
        $this->store = new InMemoryStore();
        $this->chat = new Chat($this->agent, $this->store);
    }

    public function testItInitiatesChatByClearingAndSavingMessages()
    {
        $messages = $this->createMock(MessageBag::class);

        $this->chat->initiate($messages);

        $this->assertCount(0, $this->store->load());

        $this->agent->assertNotCalled();
    }

    public function testItSubmitsUserMessageAndReturnsAssistantMessage()
    {
        $userMessage = Message::ofUser($userPrompt = 'Hello, how are you?');
        $assistantContent = 'I am doing well, thank you!';
        $assistantSources = ['https://example.com'];

        $response = new MockResponse($assistantContent);
        $response->getMetadata()->add('sources', $assistantSources);

        $this->agent->addResponse($userPrompt, $response);

        $result = $this->chat->submit($userMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($assistantContent, $result->getContent());
        $this->assertSame($assistantSources, $result->getMetadata()->get('sources', []));
        $this->assertCount(2, $this->store->load());

        $this->agent->assertCallCount(1);
        $this->agent->assertCalledWith($userPrompt);
    }

    public function testItAppendsMessagesToExistingConversation()
    {
        $existingUserMessage = Message::ofUser('What is the weather?');
        $existingAssistantMessage = Message::ofAssistant('I cannot provide weather information.');

        $existingMessages = new MessageBag();
        $existingMessages->add($existingUserMessage);
        $existingMessages->add($existingAssistantMessage);

        $this->store->save($existingMessages);

        $newUserMessage = Message::ofUser($newUserPrompt = 'Can you help with programming?');
        $newAssistantContent = 'Yes, I can help with programming!';

        $this->agent->addResponse($newUserPrompt, $newAssistantContent);

        $result = $this->chat->submit($newUserMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($newAssistantContent, $result->getContent());
        $this->assertCount(4, $this->store->load());

        $this->agent->assertCallCount(1);
        $this->agent->assertCalledWith($newUserPrompt);
    }

    public function testItHandlesEmptyMessageStore()
    {
        $userMessage = Message::ofUser($userPrompt = 'First message');
        $assistantContent = 'First response';

        $this->agent->addResponse($userPrompt, $assistantContent);

        $result = $this->chat->submit($userMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($assistantContent, $result->getContent());
        $this->assertCount(2, $this->store->load());

        $this->agent->assertCallCount(1);
        $this->agent->assertCalledWith($userPrompt);
    }
}
