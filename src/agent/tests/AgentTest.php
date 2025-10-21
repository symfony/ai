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
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\PlainConverter;

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

    public function testConstructorSetsAgentOnAgentAwareProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);

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

        $agent = new Agent($platform, 'gpt-4o', [$agentAwareProcessor]);

        $this->assertSame($agent, $agentAwareProcessor->agent);
    }

    public function testConstructorThrowsExceptionForInvalidInputProcessor()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $invalidProcessor = new \stdClass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Processor "stdClass" must implement "%s".', InputProcessorInterface::class));

        /* @phpstan-ignore-next-line */
        new Agent($platform, 'gpt-4o', [$invalidProcessor]);
    }

    public function testConstructorThrowsExceptionForInvalidOutputProcessor()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $invalidProcessor = new \stdClass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Processor "stdClass" must implement "%s".', OutputProcessorInterface::class));

        /* @phpstan-ignore-next-line */
        new Agent($platform, 'gpt-4o', [], [$invalidProcessor]);
    }

    public function testAgentExposesHisModel()
    {
        $platform = $this->createMock(PlatformInterface::class);

        $agent = new Agent($platform, 'gpt-4o');

        $this->assertEquals('gpt-4o', $agent->getModel());
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
}
