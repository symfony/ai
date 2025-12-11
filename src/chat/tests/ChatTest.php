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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class ChatTest extends TestCase
{
    private AgentInterface&MockObject $agent;
    private InMemoryStore $store;
    private Chat $chat;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(AgentInterface::class);
        $this->store = new InMemoryStore();
        $this->chat = new Chat($this->agent, $this->store);
    }

    public function testItInitiatesChatByClearingAndSavingMessages()
    {
        $messages = $this->createMock(MessageBag::class);

        $this->chat->initiate($messages);

        $this->assertCount(0, $this->store->load());
    }

    public function testItSubmitsUserMessageAndReturnsAssistantMessage()
    {
        $userMessage = Message::ofUser('Hello, how are you?');
        $assistantContent = 'I am doing well, thank you!';

        $textResult = new TextResult($assistantContent);

        $this->agent->expects($this->once())
            ->method('call')
            ->with($this->callback(function (MessageBag $messages) use ($userMessage) {
                $messagesArray = $messages->getMessages();

                return end($messagesArray) === $userMessage;
            }))
            ->willReturn($textResult);

        $result = $this->chat->submit($userMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($assistantContent, $result->getContent());
        $this->assertCount(2, $this->store->load());
    }

    public function testItAppendsMessagesToExistingConversation()
    {
        $existingUserMessage = Message::ofUser('What is the weather?');
        $existingAssistantMessage = Message::ofAssistant('I cannot provide weather information.');

        $existingMessages = new MessageBag();
        $existingMessages->add($existingUserMessage);
        $existingMessages->add($existingAssistantMessage);

        $newUserMessage = Message::ofUser('Can you help with programming?');
        $newAssistantContent = 'Yes, I can help with programming!';

        $textResult = new TextResult($newAssistantContent);

        $this->agent->expects($this->once())
            ->method('call')
            ->willReturn($textResult);

        $result = $this->chat->submit($newUserMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($newAssistantContent, $result->getContent());
        $this->assertCount(2, $this->store->load());
    }

    public function testItHandlesEmptyMessageStore()
    {
        $userMessage = Message::ofUser('First message');
        $assistantContent = 'First response';

        $textResult = new TextResult($assistantContent);

        $this->agent->expects($this->once())
            ->method('call')
            ->with($this->callback(function (MessageBag $messages) {
                $messagesArray = $messages->getMessages();

                return 1 === \count($messagesArray);
            }))
            ->willReturn($textResult);

        $result = $this->chat->submit($userMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($assistantContent, $result->getContent());
        $this->assertCount(2, $this->store->load());
    }
}
