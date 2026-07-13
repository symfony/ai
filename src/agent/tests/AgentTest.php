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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\AgentResult;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\ResultAwareContextProcessorInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Tests\Fixtures\MessageBagCapturingProcessor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AgentTest extends TestCase
{
    public function testConstructorInitializesWithDefaults()
    {
        $agent = new Agent($this->createMock(PlatformInterface::class), 'gpt-4o');

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testAgentExposesHisModel()
    {
        $agent = new Agent($this->createMock(PlatformInterface::class), 'gpt-4o');

        $this->assertSame('gpt-4o', $agent->getModel());
    }

    public function testGetNameReturnsDefaultName()
    {
        $agent = new Agent($this->createMock(PlatformInterface::class), 'gpt-4o');

        $this->assertSame('agent', $agent->getName());
    }

    public function testGetNameReturnsProvidedName()
    {
        $agent = new Agent($this->createMock(PlatformInterface::class), 'gpt-4o', name: 'my-agent');

        $this->assertSame('my-agent', $agent->getName());
    }

    public function testCallNormalizesStringInputIntoUserMessage()
    {
        $processor = new MessageBagCapturingProcessor();

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor]);
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

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor]);
        $agent->call($userMessage)->getResult();

        $this->assertInstanceOf(MessageBag::class, $processor->messageBag);
        $this->assertSame([$userMessage], $processor->messageBag->getMessages());
    }

    public function testCallKeepsAGivenMessageBagAsIs()
    {
        $processor = new MessageBagCapturingProcessor();
        $messageBag = new MessageBag(Message::ofUser('Hello there'));

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor]);
        $agent->call($messageBag)->getResult();

        $this->assertSame($messageBag, $processor->messageBag);
    }

    public function testConstructorThrowsExceptionForInvalidContextProcessor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Context processor "stdClass" must implement "%s".', ContextProcessorInterface::class));

        /* @phpstan-ignore-next-line argument.type */
        new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [new \stdClass()]);
    }

    public function testTheInstructionEndsUpAsTheSystemMessage()
    {
        $processor = new MessageBagCapturingProcessor();

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor], instruction: 'You are a helpful assistant.');
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(MessageBag::class, $processor->messageBag);
        $messages = $processor->messageBag->getMessages();
        $this->assertInstanceOf(SystemMessage::class, $messages[0]);
        $this->assertSame('You are a helpful assistant.', $messages[0]->getContent());
    }

    public function testAnInstructionPassedPerCallIsApplied()
    {
        $processor = new MessageBagCapturingProcessor();

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor]);
        $agent->call('Hello there', new Context(new Instruction('Be brief.')))->getResult();

        $this->assertInstanceOf(MessageBag::class, $processor->messageBag);
        $this->assertSame('Be brief.', $processor->messageBag->getMessages()[0]->getContent());
    }

    public function testContextProcessorsRunOnEveryCall()
    {
        $processor = new class implements ContextProcessorInterface {
            public int $calls = 0;

            public static function supportedTypes(): array
            {
                return [];
            }

            public function process(AgentRequest $request, AgentContext $context): void
            {
                ++$this->calls;
            }
        };

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor]);
        $agent->call('Hello there')->getResult();
        $agent->call('Hello again')->getResult();

        $this->assertSame(2, $processor->calls);
    }

    public function testATypedContextProcessorOnlyRunsWhenTheContextCarriesItsType()
    {
        $processor = new class implements ContextProcessorInterface {
            public int $calls = 0;

            public static function supportedTypes(): array
            {
                return [Instruction::class];
            }

            public function process(AgentRequest $request, AgentContext $context): void
            {
                ++$this->calls;
            }
        };

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor]);

        $agent->call('Hello there')->getResult();
        $this->assertSame(0, $processor->calls);

        $agent->call('Hello there', new Context(new Instruction('Be brief.')))->getResult();
        $this->assertSame(1, $processor->calls);
    }

    public function testAResultAwareContextProcessorCanReplaceTheResult()
    {
        $processor = new class implements ResultAwareContextProcessorInterface {
            public static function supportedTypes(): array
            {
                return [];
            }

            public function process(AgentRequest $request, AgentContext $context): void
            {
            }

            public function processResult(AgentResult $result, AgentContext $context): void
            {
                $result->setResult(new TextResult('Replaced'));
            }
        };

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$processor]);

        $this->assertSame('Replaced', $agent->call('Hello there')->getContent());
    }

    public function testTheModelOptionOverridesTheConfiguredModel()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform
            ->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o-mini', $this->anything(), $this->anything())
            ->willReturn($this->deferred(new TextResult('Hi')));

        $agent = new Agent($platform, 'gpt-4o');
        $agent->call('Hello there', options: ['model' => 'gpt-4o-mini'])->getResult();
    }

    public function testTheModelOptionMustBeANonEmptyString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "model" must be a non-empty string.');

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o');
        $agent->call('Hello there', options: ['model' => ''])->getResult();
    }

    public function testCallAllowsAudioInput()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform
            ->expects($this->once())
            ->method('invoke')
            ->willReturn($this->deferred(new TextResult('Transcribed')));

        $messages = new MessageBag(new UserMessage(new Text('Transcribe'), Audio::fromFile(\dirname(__DIR__, 3).'/fixtures/audio.mp3')));

        $agent = new Agent($platform, 'gpt-4o');

        $this->assertSame('Transcribed', $agent->call($messages)->getContent());
    }

    public function testCallAllowsImageInput()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform
            ->expects($this->once())
            ->method('invoke')
            ->willReturn($this->deferred(new TextResult('Described')));

        $messages = new MessageBag(new UserMessage(new Text('Describe'), Image::fromFile(\dirname(__DIR__, 3).'/fixtures/image.jpg')));

        $agent = new Agent($platform, 'gpt-4o');

        $this->assertSame('Described', $agent->call($messages)->getContent());
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

        $execution = (new Agent($platform, 'gpt-4'))->call('Hello', options: ['stream' => true]);
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

    public function testCallPassesOptionsToInvoke()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));

        $platform = $this->createMock(PlatformInterface::class);
        $platform
            ->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o', $messages, ['temperature' => 0.5])
            ->willReturn($this->deferred(new TextResult('Hi')));

        $agent = new Agent($platform, 'gpt-4o');
        $agent->call($messages, options: ['temperature' => 0.5])->getResult();
    }

    public function testConstructorAcceptsTraversableProcessors()
    {
        $processor = new MessageBagCapturingProcessor();

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', new \ArrayIterator([$processor]));
        $agent->call('Hello there')->getResult();

        $this->assertInstanceOf(MessageBag::class, $processor->messageBag);
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
        $platform = new InMemoryPlatform(static function () use (&$invocations, $toolCall): ResultInterface {
            if (++$invocations > 10) {
                throw new \LogicException('The tool calling loop was not capped by max tool calls.');
            }

            return new ToolCallResult([$toolCall]);
        });

        $agent = new Agent($platform, 'gpt-4', toolbox: $toolbox, maxToolCalls: 3);

        $this->expectException(MaxIterationsExceededException::class);
        $this->expectExceptionMessage('Maximum number of tool calling iterations (3) exceeded.');

        $agent->call(new MessageBag())->getResult();
    }

    private function deferred(ResultInterface $result): DeferredResult
    {
        return new DeferredResult(new PlainConverter($result), $this->createMock(RawResultInterface::class), []);
    }
}
