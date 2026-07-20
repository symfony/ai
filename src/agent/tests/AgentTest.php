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
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException as PlatformInvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\Exception\InterruptedException;
use Symfony\AI\Platform\Result\InterruptionSignal;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;

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

        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [$agentAwareProcessor]);
        $agent->call(new MessageBag());

        $this->assertSame($agent, $agentAwareProcessor->agent);
    }

    public function testConstructorThrowsExceptionForInvalidInputProcessor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Input processor "stdClass" must implement "%s".', InputProcessorInterface::class));

        /** @phpstan-ignore-next-line argument.type */
        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [new \stdClass()]);
        $agent->call(new MessageBag());
    }

    public function testConstructorThrowsExceptionForInvalidOutputProcessor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Output processor "stdClass" must implement "%s".', OutputProcessorInterface::class));

        /** @phpstan-ignore-next-line argument.type */
        $agent = new Agent(new InMemoryPlatform('Hi'), 'gpt-4o', [], [new \stdClass()]);
        $agent->call(new MessageBag());
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
        $actualResult = $agent->call($messages);

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
        $actualResult = $agent->call($messages);

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
        $actualResult = $agent->call($messages);

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
        $actualResult = $agent->call($messages);

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
        $actualResult = $agent->call($messages, $options);

        $this->assertSame($result, $actualResult);
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

    public function testCallAbortsImmediatelyWhenSignalAlreadyInterrupted()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $inputProcessor->expects($this->never())->method('processInput');

        $signal = new InterruptionSignal();
        $signal->interrupt();

        $agent = new Agent($platform, 'gpt-4', [$inputProcessor]);

        $this->expectException(InterruptedException::class);
        $agent->call(new MessageBag(new UserMessage(new Text('hi'))), ['interruption_signal' => $signal]);
    }

    public function testCallAbortsBetweenInputProcessorsAndPlatform()
    {
        $signal = new InterruptionSignal();

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $inputProcessor->expects($this->once())
            ->method('processInput')
            ->willReturnCallback(static function () use ($signal): void {
                $signal->interrupt();
            });

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $agent = new Agent($platform, 'gpt-4', [$inputProcessor]);

        $this->expectException(InterruptedException::class);
        $agent->call(new MessageBag(new UserMessage(new Text('hi'))), ['interruption_signal' => $signal]);
    }

    public function testCallAbortsBetweenPlatformAndOutputProcessors()
    {
        $signal = new InterruptionSignal();
        $rawResult = $this->createStub(RawResultInterface::class);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(static function () use ($signal, $rawResult): DeferredResult {
                $signal->interrupt();

                return new DeferredResult(new PlainConverter(new TextResult('hello')), $rawResult);
            });

        $outputProcessor = $this->createMock(OutputProcessorInterface::class);
        $outputProcessor->expects($this->never())->method('processOutput');

        $agent = new Agent($platform, 'gpt-4', [], [$outputProcessor]);

        $this->expectException(InterruptedException::class);
        $agent->call(new MessageBag(new UserMessage(new Text('hi'))), ['interruption_signal' => $signal]);
    }

    public function testCallRejectsInvalidSignalType()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $agent = new Agent($platform, 'gpt-4');

        $this->expectException(PlatformInvalidArgumentException::class);
        $this->expectExceptionMessage('The "interruption_signal" option must be an instance of');
        $agent->call(new MessageBag(new UserMessage(new Text('hi'))), ['interruption_signal' => 'not-a-signal']);
    }
}
