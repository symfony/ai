<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Chat;
use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

#[CoversClass(Chat::class)]
#[UsesClass(Message::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(TextResult::class)]
#[Small]
final class ChatTest extends TestCase
{
    private AgentInterface&MockObject $agent;
    private MessageStoreInterface&MockObject $store;
    private Chat $chat;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(AgentInterface::class);
        $this->store = $this->createMock(MessageStoreInterface::class);
        $this->chat = new Chat($this->agent, $this->store);
    }

    public function testItInitiatesChatByClearingAndSavingMessages()
    {
        $messages = $this->createMock(MessageBag::class);

        $this->store->expects($this->once())
            ->method('clear');

        $this->store->expects($this->once())
            ->method('save')
            ->with($messages);

        $this->chat->initiate($messages);
    }

    public function testItSubmitsUserMessageAndReturnsAssistantMessage()
    {
        $userMessage = Message::ofUser('Hello, how are you?');
        $existingMessages = new MessageBag();
        $assistantContent = 'I am doing well, thank you!';

        $textResult = new TextResult($assistantContent);

        $this->store->expects($this->once())
            ->method('load')
            ->willReturn($existingMessages);

        $this->agent->expects($this->once())
            ->method('call')
            ->with($this->callback(function (MessageBag $messages) use ($userMessage) {
                $messagesArray = $messages->getMessages();

                return end($messagesArray) === $userMessage;
            }))
            ->willReturn($textResult);

        $this->store->expects($this->once())
            ->method('save')
            ->with($this->callback(function (MessageBag $messages) use ($userMessage, $assistantContent) {
                $messagesArray = $messages->getMessages();
                $lastTwo = \array_slice($messagesArray, -2);

                return 2 === \count($lastTwo)
                    && $lastTwo[0] === $userMessage
                    && $lastTwo[1] instanceof AssistantMessage
                    && $lastTwo[1]->content === $assistantContent;
            }));

        $result = $this->chat->submit($userMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($assistantContent, $result->content);
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

        $this->store->expects($this->once())
            ->method('load')
            ->willReturn($existingMessages);

        $this->agent->expects($this->once())
            ->method('call')
            ->with($this->callback(function (MessageBag $messages) {
                $messagesArray = $messages->getMessages();

                return 3 === \count($messagesArray);
            }))
            ->willReturn($textResult);

        $this->store->expects($this->once())
            ->method('save')
            ->with($this->callback(function (MessageBag $messages) {
                $messagesArray = $messages->getMessages();

                return 4 === \count($messagesArray);
            }));

        $result = $this->chat->submit($newUserMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($newAssistantContent, $result->content);
    }

    public function testItHandlesEmptyMessageStore()
    {
        $userMessage = Message::ofUser('First message');
        $emptyMessages = new MessageBag();
        $assistantContent = 'First response';

        $textResult = new TextResult($assistantContent);

        $this->store->expects($this->once())
            ->method('load')
            ->willReturn($emptyMessages);

        $this->agent->expects($this->once())
            ->method('call')
            ->with($this->callback(function (MessageBag $messages) {
                $messagesArray = $messages->getMessages();

                return 1 === \count($messagesArray);
            }))
            ->willReturn($textResult);

        $this->store->expects($this->once())
            ->method('save');

        $result = $this->chat->submit($userMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($assistantContent, $result->content);
    }
}
