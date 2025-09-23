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
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\MockResponse;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

#[CoversClass(MockAgent::class)]
#[Small]
final class MockAgentTest extends TestCase
{
    public function testConstructorWithDefaultValues()
    {
        $agent = new MockAgent();

        $this->assertSame([], $agent->getResponses());
    }

    public function testConstructorWithPredefinedResponses()
    {
        $responses = ['hello' => 'Hi there!', 'goodbye' => 'See you later!'];
        $agent = new MockAgent($responses);

        $this->assertSame($responses, $agent->getResponses());
    }

    public function testCallThrowsExceptionForUnknownInput()
    {
        $agent = new MockAgent();

        $messages = new MessageBag(Message::ofUser('unknown input'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No response configured for input "unknown input".');

        $agent->call($messages);
    }

    public function testCallReturnsPredefinedResponse()
    {
        $responses = ['hello' => 'Hi there!'];
        $agent = new MockAgent($responses);

        $messages = new MessageBag(Message::ofUser('hello'));
        $result = $agent->call($messages);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hi there!', $result->getContent());
    }

    public function testCallHandlesMultipleTextContents()
    {
        $agent = new MockAgent(['hello world' => 'Response to combined text']);

        $message = Message::ofUser('hello', ' world');
        $messages = new MessageBag($message);
        $result = $agent->call($messages);

        $this->assertSame('Response to combined text', $result->getContent());
    }

    public function testAddResponse()
    {
        $agent = new MockAgent();
        $returnedAgent = $agent->addResponse('test', 'test response');

        $this->assertSame($agent, $returnedAgent);
        $this->assertSame(['test' => 'test response'], $agent->getResponses());

        $messages = new MessageBag(Message::ofUser('test'));
        $result = $agent->call($messages);

        $this->assertSame('test response', $result->getContent());
    }

    public function testClearResponses()
    {
        $responses = ['hello' => 'Hi there!', 'goodbye' => 'See you later!'];
        $agent = new MockAgent($responses);
        $returnedAgent = $agent->clearResponses();

        $this->assertSame($agent, $returnedAgent);
        $this->assertSame([], $agent->getResponses());
    }

    public function testCallWithOptions()
    {
        $agent = new MockAgent(['test' => 'configured response']);
        $messages = new MessageBag(Message::ofUser('test'));
        $options = ['temperature' => 0.7, 'max_tokens' => 100];

        $result = $agent->call($messages, $options);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('configured response', $result->getContent());
    }

    public function testCallWithMultipleMessages()
    {
        $agent = new MockAgent(['latest' => 'Response to latest message']);

        $messages = new MessageBag(
            Message::ofUser('first message'),
            Message::ofUser('latest')
        );
        $result = $agent->call($messages);

        $this->assertSame('Response to latest message', $result->getContent());
    }

    public function testFluentInterface()
    {
        $agent = new MockAgent();

        $result = $agent
            ->addResponse('hello', 'Hi!')
            ->addResponse('bye', 'Goodbye!');

        $this->assertSame($agent, $result);
        $this->assertSame(['hello' => 'Hi!', 'bye' => 'Goodbye!'], $agent->getResponses());

        $messages = new MessageBag(Message::ofUser('hello'));
        $response = $agent->call($messages);
        $this->assertSame('Hi!', $response->getContent());
    }

    public function testTracksCallCount()
    {
        $agent = new MockAgent(['test' => 'test response']);

        $this->assertSame(0, $agent->getCallCount());

        $messages = new MessageBag(Message::ofUser('test'));
        $agent->call($messages);

        $this->assertSame(1, $agent->getCallCount());

        $agent->call($messages);
        $this->assertSame(2, $agent->getCallCount());
    }

    public function testGetCalls()
    {
        $agent = new MockAgent(['hello' => 'Hi there!', 'bye' => 'Goodbye!']);

        $messages1 = new MessageBag(Message::ofUser('hello'));
        $messages2 = new MessageBag(Message::ofUser('bye'));
        $options = ['temperature' => 0.5];

        $agent->call($messages1, $options);
        $agent->call($messages2);

        $calls = $agent->getCalls();
        $this->assertCount(2, $calls);

        $this->assertSame($messages1, $calls[0]['messages']);
        $this->assertSame($options, $calls[0]['options']);
        $this->assertSame('hello', $calls[0]['input']);
        $this->assertSame('Hi there!', $calls[0]['response']);

        $this->assertSame($messages2, $calls[1]['messages']);
        $this->assertSame([], $calls[1]['options']);
        $this->assertSame('bye', $calls[1]['input']);
        $this->assertSame('Goodbye!', $calls[1]['response']);
    }

    public function testGetCall()
    {
        $agent = new MockAgent(['test' => 'test response']);
        $messages = new MessageBag(Message::ofUser('test'));

        $agent->call($messages, ['param' => 'value']);

        $call = $agent->getCall(0);
        $this->assertSame($messages, $call['messages']);
        $this->assertSame(['param' => 'value'], $call['options']);
        $this->assertSame('test', $call['input']);
        $this->assertSame('test response', $call['response']);
    }

    public function testGetCallThrowsExceptionForInvalidIndex()
    {
        $agent = new MockAgent();

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('Call at index 0 does not exist. Only 0 calls have been made.');

        $agent->getCall(0);
    }

    public function testGetLastCall()
    {
        $agent = new MockAgent(['test' => 'test response']);
        $messages = new MessageBag(Message::ofUser('test'));

        $agent->call($messages);
        $agent->call($messages);

        $lastCall = $agent->getLastCall();
        $this->assertSame($messages, $lastCall['messages']);
        $this->assertSame('test', $lastCall['input']);
    }

    public function testGetLastCallThrowsExceptionWhenNoCalls()
    {
        $agent = new MockAgent();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No calls have been made yet.');

        $agent->getLastCall();
    }

    public function testAssertCallCount()
    {
        $agent = new MockAgent(['test' => 'test response']);
        $messages = new MessageBag(Message::ofUser('test'));

        $agent->assertCallCount(0);

        $agent->call($messages);
        $agent->assertCallCount(1);

        $this->expectException(\AssertionError::class);
        $this->expectExceptionMessage('Expected 3 calls, but 1 calls were made.');
        $agent->assertCallCount(3);
    }

    public function testAssertCalled()
    {
        $agent = new MockAgent();

        $this->expectException(\AssertionError::class);
        $this->expectExceptionMessage('Expected at least one call, but no calls were made.');
        $agent->assertCalled();
    }

    public function testAssertCalledSucceeds()
    {
        $agent = new MockAgent(['test' => 'test response']);
        $messages = new MessageBag(Message::ofUser('test'));

        $agent->call($messages);
        $agent->assertCalled(); // Should not throw

        $this->expectNotToPerformAssertions(); // Test passes if no exception is thrown
    }

    public function testAssertNotCalled()
    {
        $agent = new MockAgent();

        $agent->assertNotCalled(); // Should not throw

        $messages = new MessageBag(Message::ofUser('test'));
        $agent->addResponse('test', 'test response');
        $agent->call($messages);

        $this->expectException(\AssertionError::class);
        $this->expectExceptionMessage('Expected no calls, but 1 calls were made.');
        $agent->assertNotCalled();
    }

    public function testAssertCalledWith()
    {
        $agent = new MockAgent(['hello' => 'hi', 'goodbye' => 'bye']);
        $messages1 = new MessageBag(Message::ofUser('hello'));
        $messages2 = new MessageBag(Message::ofUser('goodbye'));

        $agent->call($messages1);
        $agent->call($messages2);

        $agent->assertCalledWith('hello');
        $agent->assertCalledWith('goodbye');

        $this->expectException(\AssertionError::class);
        $this->expectExceptionMessage('Expected to be called with input "unknown", but it was not found in any of the 2 calls made.');
        $agent->assertCalledWith('unknown');
    }

    public function testReset()
    {
        $agent = new MockAgent(['test' => 'test response']);
        $messages = new MessageBag(Message::ofUser('test'));

        $agent->call($messages);
        $agent->call($messages);

        $this->assertSame(2, $agent->getCallCount());
        $this->assertCount(2, $agent->getCalls());

        $returnedAgent = $agent->reset();

        $this->assertSame($agent, $returnedAgent);
        $this->assertSame(0, $agent->getCallCount());
        $this->assertCount(0, $agent->getCalls());
    }

    public function testWorksWithMockResponse()
    {
        $mockResponse = new MockResponse('Mock response content');
        $agent = new MockAgent(['hello' => $mockResponse]);

        $messages = new MessageBag(Message::ofUser('hello'));
        $result = $agent->call($messages);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Mock response content', $result->getContent());
    }

    public function testMockResponseCreate()
    {
        $mockResponse = MockResponse::create('Created response');

        $this->assertInstanceOf(MockResponse::class, $mockResponse);
        $this->assertSame('Created response', $mockResponse->getContent());
        $this->assertInstanceOf(TextResult::class, $mockResponse->toResult());
        $this->assertSame('Created response', $mockResponse->toResult()->getContent());
    }

    public function testMixedResponseTypes()
    {
        $agent = new MockAgent([
            'string' => 'String response',
            'mock' => new MockResponse('Mock response'),
        ]);

        $messages1 = new MessageBag(Message::ofUser('string'));
        $result1 = $agent->call($messages1);
        $this->assertSame('String response', $result1->getContent());

        $messages2 = new MessageBag(Message::ofUser('mock'));
        $result2 = $agent->call($messages2);
        $this->assertSame('Mock response', $result2->getContent());
    }

    public function testCallableResponse()
    {
        $agent = new MockAgent();
        $agent->addResponse('dynamic', function ($messages, $options, $input) {
            return "Dynamic response for: {$input}";
        });

        $messages = new MessageBag(Message::ofUser('dynamic'));
        $result = $agent->call($messages);

        $this->assertSame('Dynamic response for: dynamic', $result->getContent());
    }

    public function testCallableResponseWithParameters()
    {
        $agent = new MockAgent();
        $agent->addResponse('test', function ($messages, $options, $input) {
            $messageCount = \count($messages->getMessages());
            $optionKeys = implode(',', array_keys($options));

            return "Input: {$input}, Messages: {$messageCount}, Options: {$optionKeys}";
        });

        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('test')
        );
        $options = ['temperature' => 0.7, 'model' => 'test-model'];
        $result = $agent->call($messages, $options);

        $this->assertSame('Input: test, Messages: 2, Options: temperature,model', $result->getContent());
    }

    public function testCallableReturningMockResponse()
    {
        $agent = new MockAgent();
        $agent->addResponse('complex', function ($messages, $options, $input) {
            return new MockResponse("Complex response for: {$input}");
        });

        $messages = new MessageBag(Message::ofUser('complex'));
        $result = $agent->call($messages);

        $this->assertSame('Complex response for: complex', $result->getContent());
    }

    public function testCallableTrackingInCalls()
    {
        $agent = new MockAgent();
        $agent->addResponse('tracked', function ($messages, $options, $input) {
            return "Tracked: {$input}";
        });

        $messages = new MessageBag(Message::ofUser('tracked'));
        $agent->call($messages);

        $calls = $agent->getCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('tracked', $calls[0]['input']);
        $this->assertSame('Tracked: tracked', $calls[0]['response']);
    }

    public function testGetNameReturnsDefaultName()
    {
        $agent = new MockAgent();

        $this->assertSame('mock', $agent->getName());
    }

    public function testGetNameReturnsProvidedName()
    {
        $agent = new MockAgent(name: 'custom');

        $this->assertSame('custom', $agent->getName());
    }
}
