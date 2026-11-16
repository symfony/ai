<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\MessageStore;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\Uid\Uuid;

final class StatefulAgentTest extends TestCase
{
    public function testItContinuesTheStoredConversation()
    {
        $requests = [];
        $platform = MockPlatformFactory::createPlatform(static function (Model $model, array $input) use (&$requests): ResultInterface {
            $requests[] = array_column($input['messages'], 'content');

            return new TextResult(\sprintf('Answer %d', \count($requests)));
        });
        $store = new InMemoryMessageStore();
        $agent = new Agent($platform, 'gpt-4', [new SystemPromptInputProcessor('Be brief.')], messageStore: $store);
        $id = Uuid::v7();

        $agent->call(MessageBag::forId($id, 'First'))->getResult();
        $messages = $agent->call(MessageBag::forId($id, 'Second'))->getMessages();

        $this->assertSame([
            ['Be brief.', 'First'],
            ['Be brief.', 'First', 'Answer 1', 'Second'],
        ], $requests);
        $this->assertSame($messages, $store->load($id));
        $this->assertCount(4, $messages);
        $this->assertNull($messages->getSystemMessage());
    }

    public function testPassingTheWholeConversationDoesNotDuplicateIt()
    {
        $store = new InMemoryMessageStore();
        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4', messageStore: $store);

        $messages = $agent->call(new MessageBag(Message::ofUser('Hello')))->getMessages();
        $messages = $agent->call($messages->with(Message::ofUser('Hello again')))->getMessages();

        $this->assertSame(
            [UserMessage::class, AssistantMessage::class, UserMessage::class, AssistantMessage::class],
            array_map(get_class(...), $store->load($messages->getId())->getMessages()),
        );
    }

    public function testItKeepsTheMetadataOfTheGivenMessages()
    {
        $store = new InMemoryMessageStore();
        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4', messageStore: $store);
        $messages = new MessageBag(Message::ofUser('Hello'));
        $messages->getMetadata()->add('user', 'alice');

        $agent->call($messages)->getResult();

        $this->assertSame('alice', $store->load($messages->getId())->getMetadata()->get('user'));
    }

    public function testPassingTheWholeConversationDoesNotAggregateItsMetadataAgain()
    {
        $store = new InMemoryMessageStore();
        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4', messageStore: $store);
        $messages = new MessageBag(Message::ofUser('Hello'));
        $messages->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 10));

        $messages = $agent->call($messages)->getMessages();
        $agent->call($messages->with(Message::ofUser('Hello again')))->getResult();

        $this->assertSame(10, $store->load($messages->getId())->getMetadata()->get('token_usage')->getPromptTokens());
    }

    public function testItSavesTheInputAndEveryToolRoundBeforeTheAnswer()
    {
        $toolCall = new ToolCall('call_1', 'clock');
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);
        $toolbox->method('execute')->willReturn(new ToolResult($toolCall, '12:00'));

        $platform = MockPlatformFactory::createPlatform(static function (Model $model, array $input): ResultInterface {
            if (1 === \count($input['messages'])) {
                return new ToolCallResult([new ToolCall('call_1', 'clock')]);
            }

            throw new \LogicException('The model is unavailable.');
        });
        $store = new InMemoryMessageStore();
        $agent = new Agent($platform, 'gpt-4', toolbox: $toolbox, messageStore: $store);
        $messages = new MessageBag(Message::ofUser('What time is it?'));

        try {
            $agent->call($messages)->getResult();
            $this->fail('The second model request is expected to fail.');
        } catch (\LogicException) {
        }

        $this->assertSame(
            [UserMessage::class, AssistantMessage::class, ToolCallMessage::class],
            array_map(get_class(...), $store->load($messages->getId())->getMessages()),
        );
    }

    public function testItFailsWhenTheConversationChangedDuringTheRun()
    {
        $store = new InMemoryMessageStore();
        $messages = new MessageBag(Message::ofUser('Hello'));
        $platform = MockPlatformFactory::createPlatform(static function () use ($store, $messages): ResultInterface {
            $store->save($store->load($messages->getId())->with(Message::ofUser('Concurrent')));

            return new TextResult('Hi');
        });
        $agent = new Agent($platform, 'gpt-4', messageStore: $store);

        $this->expectException(ConflictException::class);

        $agent->call($messages)->getResult();
    }
}
