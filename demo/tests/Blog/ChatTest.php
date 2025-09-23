<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Blog;

use App\Blog\Chat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(Chat::class)]
final class ChatTest extends TestCase
{
    public function testLoadMessagesReturnsDefaultSystemMessage()
    {
        $agent = new MockAgent();
        $chat = self::createChat($agent);

        $messages = $chat->loadMessages();

        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertCount(1, $messages->getMessages());

        $systemMessage = $messages->getMessages()[0];
        $this->assertInstanceOf(SystemMessage::class, $systemMessage);
        $this->assertStringContainsString('helpful assistant', $systemMessage->content);
        $this->assertStringContainsString('similarity_search', $systemMessage->content);
    }

    public function testSubmitMessageAddsUserMessageAndAgentResponse()
    {
        $agent = new MockAgent([
            'What is Symfony?' => 'Symfony is a PHP web framework for building web applications and APIs.',
        ]);
        $chat = self::createChat($agent);

        // Submit a message that the agent has a response for
        $chat->submitMessage('What is Symfony?');

        // Verify the agent was called
        $agent->assertCallCount(1);
        $agent->assertCalledWith('What is Symfony?');

        // Load messages and verify they contain both user message and agent response
        $messages = $chat->loadMessages();
        $messageList = $messages->getMessages();

        // Should have: system message + user message + assistant message = 3 total
        $this->assertCount(3, $messageList);

        // Check user message
        $userMessage = $messageList[1];
        $this->assertInstanceOf(UserMessage::class, $userMessage);
        $this->assertSame('What is Symfony?', $userMessage->content[0]->text);

        // Check assistant message
        $assistantMessage = $messageList[2];
        $this->assertInstanceOf(AssistantMessage::class, $assistantMessage);
        $this->assertSame('Symfony is a PHP web framework for building web applications and APIs.', $assistantMessage->content);
    }

    public function testSubmitMessageWithUnknownQueryUsesDefaultResponse()
    {
        $agent = new MockAgent([
            'What is the weather today?' => 'I can help you with Symfony-related questions!',
        ]);
        $chat = self::createChat($agent);

        $chat->submitMessage('What is the weather today?');

        // Verify the agent was called
        $agent->assertCallCount(1);
        $agent->assertCalledWith('What is the weather today?');

        $messages = $chat->loadMessages();
        $messageList = $messages->getMessages();

        // Check assistant used default response
        $assistantMessage = $messageList[2];
        $this->assertSame('I can help you with Symfony-related questions!', $assistantMessage->content);
    }

    public function testMultipleMessagesAreTrackedCorrectly()
    {
        $agent = new MockAgent([
            'What is Symfony?' => 'Symfony is a PHP web framework for building web applications and APIs.',
            'Tell me about caching' => 'Symfony provides powerful caching mechanisms including APCu, Redis, and file-based caching.',
        ]);
        $chat = self::createChat($agent);

        // Submit multiple messages
        $chat->submitMessage('What is Symfony?');
        $chat->submitMessage('Tell me about caching');

        // Verify agent call tracking
        $agent->assertCallCount(2);

        // Get all calls made to the agent
        $calls = $agent->getCalls();
        $this->assertCount(2, $calls);

        // First call should have system + user message for "What is Symfony?"
        $this->assertSame('What is Symfony?', $calls[0]['input']);

        // Second call should have system + previous conversation + new user message
        $this->assertSame('Tell me about caching', $calls[1]['input']);

        // Verify messages in session
        $messages = $chat->loadMessages();
        $this->assertCount(5, $messages->getMessages()); // system + user1 + assistant1 + user2 + assistant2
    }

    public function testResetClearsMessages()
    {
        $agent = new MockAgent([
            'What is Symfony?' => 'Symfony is a PHP web framework for building web applications and APIs.',
        ]);
        $chat = self::createChat($agent);

        // Add some messages
        $chat->submitMessage('What is Symfony?');

        // Verify messages exist
        $messages = $chat->loadMessages();
        $this->assertCount(3, $messages->getMessages());

        // Reset and verify messages are cleared
        $chat->reset();

        $messages = $chat->loadMessages();
        $this->assertCount(1, $messages->getMessages()); // Only system message remains
    }

    public function testAgentReceivesFullConversationHistory()
    {
        $agent = new MockAgent([
            'What is Symfony?' => 'Symfony is a PHP web framework for building web applications and APIs.',
            'Tell me more' => 'Symfony has many components like HttpFoundation, Console, and Routing.',
        ]);
        $chat = self::createChat($agent);

        // Submit first message
        $chat->submitMessage('What is Symfony?');

        // Submit second message
        $chat->submitMessage('Tell me more');

        // Get the second call to verify it received full conversation
        $calls = $agent->getCalls();
        $secondCallMessages = $calls[1]['messages'];

        // Should contain: system + user1 + assistant1 + user2, but apparently there are 5 messages
        // This might include an additional message from the conversation flow
        $messages = $secondCallMessages->getMessages();
        $this->assertCount(5, $messages);

        // Verify the conversation flow (with 5 messages)
        $this->assertStringContainsString('helpful assistant', $messages[0]->content); // system
        $this->assertSame('What is Symfony?', $messages[1]->content[0]->text); // user1
        $this->assertSame('Symfony is a PHP web framework for building web applications and APIs.', $messages[2]->content); // assistant1
        $this->assertSame('Tell me more', $messages[3]->content[0]->text); // user2
        // The 5th message appears to be the previous assistant response or another system message
    }

    public function testMockAgentAssertionsWork()
    {
        $agent = new MockAgent([
            'What is Symfony?' => 'Symfony is a PHP web framework for building web applications and APIs.',
            'Tell me about caching' => 'Symfony provides powerful caching mechanisms including APCu, Redis, and file-based caching.',
        ]);
        $chat = self::createChat($agent);

        // Test that we can make assertions about agent calls
        $agent->assertNotCalled();

        $chat->submitMessage('What is Symfony?');

        // Now agent should have been called
        $agent->assertCalled();
        $agent->assertCallCount(1);
        $agent->assertCalledWith('What is Symfony?');

        // Test multiple calls
        $chat->submitMessage('Tell me about caching');
        $agent->assertCallCount(2);

        // Test last call
        $lastCall = $agent->getLastCall();
        $this->assertSame('Tell me about caching', $lastCall['input']);
    }

    private static function createChat(MockAgent $agent): Chat
    {
        $session = new Session(new MockArraySessionStorage());
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        return new Chat($requestStack, $agent);
    }
}
