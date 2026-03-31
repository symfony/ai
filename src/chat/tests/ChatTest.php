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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\StreamResult;

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
        $agent = new MockAgent();

        $chat = new Chat($agent, new InMemoryStore());
        $chat->initiate(new MessageBag());

        $agent->assertNotCalled();
        $this->assertCount(0, $this->store->load());
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
        $this->assertCount(2, $this->store->load('_chat'));

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

        $this->store->save($existingMessages, '_chat');

        $newUserMessage = Message::ofUser($newUserPrompt = 'Can you help with programming?');
        $newAssistantContent = 'Yes, I can help with programming!';

        $this->agent->addResponse($newUserPrompt, $newAssistantContent);

        $result = $this->chat->submit($newUserMessage);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertSame($newAssistantContent, $result->getContent());
        $this->assertCount(4, $this->store->load('_chat'));

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
        $this->assertCount(2, $this->store->load('_chat'));

        $this->agent->assertCallCount(1);
        $this->agent->assertCalledWith($userPrompt);
    }

    public function testItStreamsResponseChunks()
    {
        $store = new InMemoryStore();

        $agent = new MockAgent([
            'Hello' => new StreamResult((static function (): \Generator {
                yield 'I am ';
                yield 'doing well!';
            })()),
        ], 'mock-stream');

        $chat = new Chat($agent, $store);

        $chunks = iterator_to_array($chat->stream(Message::ofUser('Hello')));

        $this->assertSame(['I am ', 'doing well!'], $chunks);

        $agent->assertCallCount(1);
        $agent->assertCalledWith('Hello');

        $stored = $store->load('_chat');
        $this->assertCount(2, $stored);

        $assistantMessage = $stored->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('I am doing well!', $assistantMessage->getContent());
    }

    public function testItStreamsAndPreservesExistingConversation()
    {
        $existingMessages = new MessageBag();
        $existingMessages->add(Message::ofUser('What is the weather?'));
        $existingMessages->add(Message::ofAssistant('I cannot provide weather information.'));

        $store = new InMemoryStore();
        $store->save($existingMessages, '_chat');

        $agent = new MockAgent([
            'Can you help?' => new StreamResult((static function (): \Generator {
                yield 'Yes, ';
                yield 'I can!';
            })()),
        ], 'mock-stream');

        $chat = new Chat($agent, $store);

        $chunks = iterator_to_array($chat->stream(Message::ofUser('Can you help?')));

        $this->assertSame(['Yes, ', 'I can!'], $chunks);

        $agent->assertCallCount(1);
        $agent->assertCalledWith('Can you help?');

        $stored = $store->load('_chat');
        $this->assertCount(4, $stored);

        $assistantMessage = $stored->getMessages()[3];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('Yes, I can!', $assistantMessage->getContent());
    }

    public function testStreamOnBranchSavesToCorrectIdentifier()
    {
        $store = new InMemoryStore();

        $agent = new MockAgent([
            'hello' => new MockResponse('Hi'),
            'stream me' => new StreamResult((static function (): \Generator {
                yield 'Streamed ';
                yield 'on branch!';
            })()),
        ]);

        $chat = new Chat($agent, $store, 'main');
        $chat->submit(Message::ofUser('hello'));

        $branched = $chat->branch('fork');

        $chunks = iterator_to_array($branched->stream(Message::ofUser('stream me')));

        $this->assertSame(['Streamed ', 'on branch!'], $chunks);

        // Streamed response saved under branch identifier, not main
        $branchStored = $store->load('fork');
        $this->assertCount(4, $branchStored);

        $assistantMessage = $branchStored->getMessages()[3];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('Streamed on branch!', $assistantMessage->getContent());

        // Main remains untouched
        $this->assertCount(2, $store->load('main'));
    }

    public function testSubmitThenStreamOnSameChatSharesMessages()
    {
        $store = new InMemoryStore();

        $agent = new MockAgent([
            'first' => new MockResponse('First reply'),
            'second' => new StreamResult((static function (): \Generator {
                yield 'Second ';
                yield 'reply!';
            })()),
        ]);

        $chat = new Chat($agent, $store, 'conv');

        $chat->submit(Message::ofUser('first'));
        $chunks = iterator_to_array($chat->stream(Message::ofUser('second')));

        $this->assertSame(['Second ', 'reply!'], $chunks);

        // Both submit and stream messages are in the same conversation
        $stored = $store->load('conv');
        $this->assertCount(4, $stored);
    }

    public function testStreamOnBranchWithDifferentAgent()
    {
        $store = new InMemoryStore();

        $mainAgent = new MockAgent([
            'hello' => new MockResponse('Main reply'),
        ]);
        $streamAgent = new MockAgent([
            'stream query' => new StreamResult((static function (): \Generator {
                yield 'Specialist ';
                yield 'stream!';
            })()),
        ]);

        $chat = new Chat($mainAgent, $store, 'main');
        $chat->submit(Message::ofUser('hello'));

        $branched = $chat->branch('specialist', $streamAgent);
        $chunks = iterator_to_array($branched->stream(Message::ofUser('stream query')));

        $this->assertSame(['Specialist ', 'stream!'], $chunks);
        $streamAgent->assertCallCount(1);

        $stored = $store->load('specialist');
        $this->assertCount(4, $stored);
    }

    public function testItCanBeBranched()
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

    public function testInitiateDoesNotDestroyOtherConversations()
    {
        $store = new InMemoryStore();

        $chat = new Chat(new MockAgent([
            'hello' => new MockResponse('Hi'),
        ]), $store, 'main');

        $chat->submit(Message::ofUser('hello'));

        $branched = $chat->branch('other');

        // Initiating the main chat should not destroy the branch
        $chat->initiate(new MessageBag());

        $this->assertCount(0, $store->load('main'));
        $this->assertCount(2, $store->load('other'));
    }

    public function testBranchCreatesIsolatedCopy()
    {
        $store = new InMemoryStore();

        $agent = new MockAgent([
            'hello' => new MockResponse('Hi'),
            'branch msg' => new MockResponse('Branch reply'),
            'main msg' => new MockResponse('Main reply'),
        ]);

        $chat = new Chat($agent, $store, 'main');
        $chat->submit(Message::ofUser('hello'));

        $branched = $chat->branch('fork');

        // Messages diverge after branching
        $branched->submit(Message::ofUser('branch msg'));
        $chat->submit(Message::ofUser('main msg'));

        // Branch has: hello + Hi + branch msg + Branch reply = 4
        $this->assertCount(4, $store->load('fork'));
        // Main has: hello + Hi + main msg + Main reply = 4
        $this->assertCount(4, $store->load('main'));

        // Verify the content is different
        $branchMessages = $store->load('fork')->getMessages();
        $mainMessages = $store->load('main')->getMessages();

        $this->assertInstanceOf(UserMessage::class, $branchMessages[2]);
        $this->assertInstanceOf(UserMessage::class, $mainMessages[2]);

        $branchContent = $branchMessages[2]->getContent();
        $mainContent = $mainMessages[2]->getContent();

        $this->assertInstanceOf(Text::class, $branchContent[0]);
        $this->assertInstanceOf(Text::class, $mainContent[0]);

        $this->assertNotSame(
            $branchContent[0]->getText(),
            $mainContent[0]->getText(),
        );
    }

    public function testBranchWithDifferentAgent()
    {
        $store = new InMemoryStore();

        $mainAgent = new MockAgent([
            'hello' => new MockResponse('Main agent reply'),
        ]);
        $specialistAgent = new MockAgent([
            'specialist query' => new MockResponse('Specialist reply'),
        ]);

        $chat = new Chat($mainAgent, $store, 'main');
        $chat->submit(Message::ofUser('hello'));

        $branched = $chat->branch('specialist', $specialistAgent);
        $result = $branched->submit(Message::ofUser('specialist query'));

        $this->assertSame('Specialist reply', $result->getContent());
        $specialistAgent->assertCallCount(1);
    }

    public function testTwoBranchesDivergeIndependently()
    {
        $store = new InMemoryStore();

        $agent = new MockAgent([
            'root' => new MockResponse('Root reply'),
            'branch A msg' => new MockResponse('A reply'),
            'branch B msg' => new MockResponse('B reply'),
        ]);

        $chat = new Chat($agent, $store, 'root');
        $chat->submit(Message::ofUser('root'));

        $branchA = $chat->branch('a');
        $branchB = $chat->branch('b');

        $branchA->submit(Message::ofUser('branch A msg'));
        $branchB->submit(Message::ofUser('branch B msg'));

        $this->assertCount(4, $store->load('a'));
        $this->assertCount(4, $store->load('b'));
        $this->assertCount(2, $store->load('root'));
    }
}
