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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Chat\Bridge\Local\InMemoryStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Result\TextResult;

#[CoversClass(Chat::class)]
#[UsesClass(Agent::class)]
#[UsesClass(Message::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(InMemoryStore::class)]
#[UsesClass(ResultPromise::class)]
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

    public function testChatCanUseAnotherAgentOnceInitialized()
    {
        $rawResult = $this->createStub(RawResultInterface::class);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn(new ResultPromise(static fn (): TextResult => new TextResult('Assistant response'), $rawResult));

        $model = $this->createMock(Model::class);

        $firstAgent = new Agent($platform, $model);

        $store = new InMemoryStore();

        $chat = new Chat($firstAgent, $store);
        $chat->submit(Message::ofUser('First message'));

        $this->assertCount(2, $store->load());

        $secondAgent = new Agent($platform, $model);
        $chat = new Chat($secondAgent, $store);
        $chat->submit(Message::ofUser('Second message'));

        $this->assertCount(4, $store->load());
    }

    public function testChatCanBeForked()
    {
        $rawResult = $this->createStub(RawResultInterface::class);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn(new ResultPromise(static fn (): TextResult => new TextResult('Assistant response'), $rawResult));

        $model = $this->createMock(Model::class);

        $firstAgent = new Agent($platform, $model);

        $store = new InMemoryStore();

        $chat = new Chat($firstAgent, $store);
        $chat->submit(Message::ofUser('First message'));

        $this->assertCount(2, $store->load());

        $forkedChat = $chat->fork('foo');
        $forkedChat->submit(Message::ofUser('First message'));
        $forkedChat->submit(Message::ofUser('Second message'));

        $this->assertCount(4, $store->load('foo'));
        $this->assertCount(2, $store->load('_message_store_memory'));

        $forkedBackChat = $forkedChat->fork('_message_store_memory');
        $forkedBackChat->submit(Message::ofUser('First message'));
        $forkedBackChat->submit(Message::ofUser('Second message'));
        $forkedBackChat->submit(Message::ofUser('Second message'));

        $this->assertCount(4, $store->load('foo'));
        $this->assertCount(8, $store->load('_message_store_memory'));
    }
}
