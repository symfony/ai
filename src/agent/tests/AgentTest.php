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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentAwareInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\Tests\Fixtures\MessageBagCapturingProcessor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException as PlatformInvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AgentTest extends TestCase
{
    public function testConstructorInitializesWithDefaults()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $agent = new Agent($platform, 'gpt-4o');

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testConstructorInitializesWithProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $outputProcessor = $this->createMock(OutputProcessorInterface::class);

        $agent = new Agent($platform, 'gpt-4o', [$inputProcessor], [$outputProcessor]);

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testAgentExposesHisModel()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $agent = new Agent($platform, 'gpt-4o');

        $this->assertSame('gpt-4o', $agent->getModel());
    }

    public function testCallNormalizesStringInputIntoUserMessage()
    {
        $processor = new MessageBagCapturingProcessor();

        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4o', [$processor]);
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(MessageBag::class, $processor->messageBag);
        $messages = $processor->messageBag->getMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('Hello there', $messages[0]->asText());
    }

    public function testCallNormalizesUserMessageIntoMessageBag()
    {
        $processor = new MessageBagCapturingProcessor();
        $userMessage = Message::ofUser('Hello there');

        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4o', [$processor]);
        $agent->call($userMessage)->getResult();

        $this->assertInstanceOf(MessageBag::class, $processor->messageBag);
        $this->assertSame([$userMessage], $processor->messageBag->getMessages());
    }

    public function testCallKeepsAGivenMessageBagAsIs()
    {
        $processor = new MessageBagCapturingProcessor();
        $messageBag = new MessageBag(Message::ofUser('Hello there'));

        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4o', [$processor]);
        $agent->call($messageBag)->getResult();

        $this->assertSame($messageBag, $processor->messageBag);
    }

    public function testSetsAgentOnAgentAwareProcessors()
    {
        $agentAwareProcessor = new class implements InputProcessorInterface, AgentAwareInterface {
            public ?AgentInterface $agent = null;

            public function processInput(Input $input): void
            {
            }

            public function setAgent(AgentInterface $agent): void
            {
                $this->agent = $agent;
            }
        };

        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4o', [$agentAwareProcessor]);
        $agent->call(new MessageBag())->getResult();

        $this->assertSame($agent, $agentAwareProcessor->agent);
    }

    public function testConstructorThrowsExceptionForInvalidInputProcessor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Input processor "stdClass" must implement "%s".', InputProcessorInterface::class));

        /** @phpstan-ignore-next-line argument.type */
        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4o', [new \stdClass()]);
        $agent->call(new MessageBag())->getResult();
    }

    public function testConstructorThrowsExceptionForInvalidOutputProcessor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Output processor "stdClass" must implement "%s".', OutputProcessorInterface::class));

        /** @phpstan-ignore-next-line argument.type */
        $agent = new Agent(MockPlatformFactory::createPlatform('Hi'), 'gpt-4o', [], [new \stdClass()]);
        $agent->call(new MessageBag())->getResult();
    }

    public function testCallProcessesInputThroughProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $modelName = 'gpt-4o';
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $result = $this->createMock(ResultInterface::class);

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $inputProcessor->expects($this->once())
            ->method('processInput')
            ->with($this->isInstanceOf(Input::class));

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with($modelName, $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, $modelName, [$inputProcessor]);
        $actualResult = $agent->call($messages)->getResult();

        $this->assertSame($result, $actualResult);
    }

    public function testCallProcessesOutputThroughProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $modelName = 'gpt-4o';
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $result = $this->createMock(ResultInterface::class);

        $outputProcessor = $this->createMock(OutputProcessorInterface::class);
        $outputProcessor->expects($this->once())
            ->method('processOutput')
            ->with($this->isInstanceOf(Output::class));

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with($modelName, $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, $modelName, [], [$outputProcessor]);
        $actualResult = $agent->call($messages)->getResult();

        $this->assertSame($result, $actualResult);
    }

    public function testCallAllowsAudioInputWithSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Audio('audio-data', 'audio/mp3')));
        $result = $this->createMock(ResultInterface::class);

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, 'gpt-4');
        $actualResult = $agent->call($messages)->getResult();

        $this->assertSame($result, $actualResult);
    }

    public function testCallAllowsImageInputWithSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Image('image-data', 'image/png')));
        $result = $this->createMock(ResultInterface::class);

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, 'gpt-4');
        $actualResult = $agent->call($messages)->getResult();

        $this->assertSame($result, $actualResult);
    }

    public function testCallPassesOptionsToInvoke()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $options = ['temperature' => 0.7, 'max_tokens' => 100];
        $result = $this->createMock(ResultInterface::class);

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new DeferredResult(new PlainConverter($result), $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', $messages, $options)
            ->willReturn($response);

        $agent = new Agent($platform, 'gpt-4');
        $actualResult = $agent->call($messages, $options)->getResult();

        $this->assertSame($result, $actualResult);
    }

    public function testCancelStopsTheActiveStreamAndCancelsItsHttpResponse()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $stream = new StreamResult((static function (): \Generator {
            yield new TextDelta('First');
            yield new TextDelta('Second');
        })());

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(new DeferredResult(
            new PlainConverter($stream),
            new RawHttpResult($response),
            ['stream' => true],
        ));

        $execution = (new Agent($platform, 'gpt-4'))->call('Hello', ['stream' => true]);
        $deltas = [];

        foreach ($execution->asStream() as $delta) {
            $this->assertInstanceOf(TextDelta::class, $delta);
            $deltas[] = $delta->getText();
            $execution->cancel();
            $execution->cancel();
        }

        $execution->cancel();

        $this->assertSame(['First'], $deltas);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $execution->getResult();
    }

    public function testConstructorAcceptsTraversableProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $outputProcessor = $this->createMock(OutputProcessorInterface::class);

        $inputProcessors = new \ArrayIterator([$inputProcessor]);
        $outputProcessors = new \ArrayIterator([$outputProcessor]);

        $agent = new Agent($platform, 'gpt-4', $inputProcessors, $outputProcessors);

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testMaxToolCallsCapsTheToolCallingLoop()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox
            ->method('getTools')
            ->willReturn([new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null)]);
        $toolbox
            ->method('execute')
            ->willReturn(new ToolResult($toolCall, 'Test response'));

        $invocations = 0;
        $platform = MockPlatformFactory::createPlatform(static function () use (&$invocations, $toolCall): ResultInterface {
            if (++$invocations > 10) {
                throw new \LogicException('The tool calling loop was not capped by max tool calls.');
            }

            return new ToolCallResult([$toolCall]);
        });

        $agent = new Agent($platform, 'gpt-4', toolbox: $toolbox, maxToolCalls: 3);

        $this->expectException(MaxIterationsExceededException::class);
        $this->expectExceptionMessage('Maximum number of tool calling iterations (3) exceeded.');

        $agent->call(new MessageBag(), [])->getResult();
    }

    public function testConvertsToolResultsWithTheGivenConverter()
    {
        $toolCall = new ToolCall('id1', 'tool1');
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null)]);
        $toolbox->method('execute')->willReturn(new ToolResult($toolCall, ['raw' => 'result']));

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturn('converted result');

        $toolMessages = [];
        $platform = MockPlatformFactory::createPlatform(static function ($model, array $input) use (&$toolMessages, $toolCall): ResultInterface|string {
            $toolMessages = array_values(array_filter($input['messages'], static fn (array $message): bool => 'tool' === $message['role']));

            return [] === $toolMessages ? new ToolCallResult([$toolCall]) : 'Final answer';
        });

        $agent = new Agent($platform, 'gpt-4', toolbox: $toolbox, toolResultConverter: new ToolResultConverter($serializer));
        $agent->call(new MessageBag(), [])->getResult();

        $this->assertCount(1, $toolMessages);
        $this->assertSame('converted result', $toolMessages[0]['content']);
    }

    public function testGetMessagesAppendsTheRunToTheGivenMessages()
    {
        $toolCall = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([new Tool(new ExecutionReference('ClassTool1', 'method1'), 'tool1', 'description1', null)]);
        $toolbox->method('execute')->willReturn(new ToolResult($toolCall, 'Sunny'));

        $toolCallResult = new ToolCallResult([$toolCall]);
        $toolCallResult->getMetadata()->add('finish_reason', 'tool_calls');
        $toolCallResult->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 10));
        $textResult = new TextResult('It is sunny.');
        $textResult->getMetadata()->add('finish_reason', 'stop');
        $textResult->getMetadata()->add('token_usage', new TokenUsage(promptTokens: 20));
        $results = [$toolCallResult, $textResult];
        $platform = MockPlatformFactory::createPlatform(static function () use (&$results): ResultInterface {
            return array_shift($results);
        });

        $agent = new Agent($platform, 'gpt-4', [new SystemPromptInputProcessor('You are a weather bot.')], toolbox: $toolbox);
        $input = new MessageBag(Message::ofUser('How is the weather?'));

        $execution = $agent->call($input);
        $messages = $execution->getMessages();

        $this->assertCount(1, $input);
        $this->assertTrue($input->getId()->equals($messages->getId()));
        $this->assertNull($messages->getSystemMessage());
        $this->assertSame([UserMessage::class, AssistantMessage::class, ToolCallMessage::class, AssistantMessage::class], array_map(get_class(...), $messages->getMessages()));
        $this->assertSame($input->getMessages()[0], $messages->getMessages()[0]);
        $answer = $messages->getMessages()[3];
        $this->assertInstanceOf(AssistantMessage::class, $answer);
        $this->assertSame('It is sunny.', $answer->asText());

        $this->assertSame('tool_calls', $messages->getMessages()[1]->getMetadata()->get('finish_reason'));
        $this->assertSame(10, $messages->getMessages()[1]->getMetadata()->get('token_usage')->getPromptTokens());
        $this->assertSame('stop', $messages->getMessages()[3]->getMetadata()->get('finish_reason'));
        $this->assertSame(20, $messages->getMessages()[3]->getMetadata()->get('token_usage')->getPromptTokens());
        $this->assertSame(30, $execution->getMetadata()->get('token_usage')->getPromptTokens());
    }

    public function testGetMessagesMapsStructuredOutputToTheAnsweredJson()
    {
        $agent = new Agent(MockPlatformFactory::createPlatform(static fn (): ResultInterface => new ObjectResult(['city' => 'Berlin'], '{"city":"Berlin"}')), 'gpt-4');

        $execution = $agent->call('Where?');

        $this->assertSame(['city' => 'Berlin'], $execution->getContent());
        $answer = $execution->getMessages()->getMessages()[1];
        $this->assertInstanceOf(AssistantMessage::class, $answer);
        $this->assertSame('{"city":"Berlin"}', $answer->asText());
    }

    public function testGetMessagesMapsTheStreamedAnswer()
    {
        $platform = MockPlatformFactory::createPlatform(static fn (): ResultInterface => new StreamResult((static function (): \Generator {
            yield new TextDelta('Hello ');
            yield new TextDelta('World');
        })()));
        $agent = new Agent($platform, 'gpt-4');

        $execution = $agent->call('Hi', ['stream' => true]);
        iterator_to_array($execution->asStream(), false);

        $answer = $execution->getMessages()->getMessages()[1];

        $this->assertInstanceOf(AssistantMessage::class, $answer);

        $this->assertSame('Hello World', $answer->asText());
    }

    public function testGetMessagesEndsWithTheResultOfTheOutputProcessors()
    {
        $result = new TextResult('original');
        $result->getMetadata()->add('finish_reason', 'stop');
        $outputProcessor = new class implements OutputProcessorInterface {
            public function processOutput(Output $output): void
            {
                $output->setResult(new TextResult('processed'));
            }
        };
        $agent = new Agent(MockPlatformFactory::createPlatform(static fn (): ResultInterface => $result), 'gpt-4', [], [$outputProcessor]);

        $answer = $agent->call('Hi')->getMessages()->getMessages()[1];

        $this->assertInstanceOf(AssistantMessage::class, $answer);
        $this->assertSame('processed', $answer->asText());
        $this->assertSame('stop', $answer->getMetadata()->get('finish_reason'));
    }

    public function testGetMessagesDoesNotMapTheResultReplacedByTheOutputProcessors()
    {
        $outputProcessor = new class implements OutputProcessorInterface {
            public function processOutput(Output $output): void
            {
                $output->setResult(new TextResult('processed'));
            }
        };
        $agent = new Agent(MockPlatformFactory::createPlatform(static fn (): ResultInterface => new ObjectResult(new \stdClass())), 'gpt-4', [], [$outputProcessor]);

        $answer = $agent->call('Hi')->getMessages()->getMessages()[1];

        $this->assertInstanceOf(AssistantMessage::class, $answer);
        $this->assertSame('processed', $answer->asText());
    }

    public function testResultWithoutAssistantMessageOnlyFailsWhenReadingTheMessages()
    {
        $result = new ObjectResult(new \stdClass());
        $execution = (new Agent(MockPlatformFactory::createPlatform(static fn (): ResultInterface => $result), 'gpt-4'))->call('Hi');

        $this->assertSame($result, $execution->getResult());

        $this->expectException(PlatformInvalidArgumentException::class);

        $execution->getMessages();
    }

    public function testGetNameReturnsDefaultName()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $agent = new Agent($platform, 'gpt-4');

        $this->assertSame('agent', $agent->getName());
    }

    public function testGetNameReturnsProvidedName()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $name = 'test';

        $agent = new Agent($platform, 'gpt-4', [], [], $name);

        $this->assertSame($name, $agent->getName());
    }
}
