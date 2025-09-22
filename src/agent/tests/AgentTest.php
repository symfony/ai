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
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentAwareInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\DynamicModelCatalog;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

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

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $agent = new Agent($platform, 'gpt-4o');

        $this->assertEquals(new Model('gpt-4o', Capability::cases()), $agent->getModel());
    }

    public function testCallProcessesInputThroughProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $modelName = 'gpt-4o';
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $result = $this->createMock(ResultInterface::class);

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $inputProcessor->expects($this->once())
            ->method('processInput')
            ->with($this->isInstanceOf(Input::class));

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

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

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $outputProcessor = $this->createMock(OutputProcessorInterface::class);
        $outputProcessor->expects($this->once())
            ->method('processOutput')
            ->with($this->isInstanceOf(Output::class));

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with($modelName, $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, $modelName, [], [$outputProcessor]);
        $actualResult = $agent->call($messages);

        $this->assertSame($result, $actualResult);
    }

    public function testCallThrowsExceptionForAudioInputWithoutSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Audio('audio-data', 'audio/mp3')));

        $modelCatalog = $this->createMock(\Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface::class);
        $model = new Model('gpt-4', [Capability::INPUT_TEXT]); // Model without INPUT_AUDIO capability

        $modelCatalog->expects($this->once())
            ->method('getModel')
            ->with('gpt-4')
            ->willReturn($model);

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn($modelCatalog);

        $this->expectException(MissingModelSupportException::class);

        $agent = new Agent($platform, 'gpt-4');
        $agent->call($messages);
    }

    public function testCallThrowsExceptionForImageInputWithoutSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Image('image-data', 'image/png')));

        $modelCatalog = $this->createMock(\Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface::class);
        $model = new Model('gpt-4', [Capability::INPUT_TEXT]); // Model without INPUT_IMAGE capability

        $modelCatalog->expects($this->once())
            ->method('getModel')
            ->with('gpt-4')
            ->willReturn($model);

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn($modelCatalog);

        $this->expectException(MissingModelSupportException::class);

        $agent = new Agent($platform, 'gpt-4');
        $agent->call($messages);
    }

    public function testCallAllowsAudioInputWithSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Audio('audio-data', 'audio/mp3')));
        $result = $this->createMock(ResultInterface::class);

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

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

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4', $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, 'gpt-4');
        $actualResult = $agent->call($messages);

        $this->assertSame($result, $actualResult);
    }

    public function testCallHandlesClientException()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $logger = $this->createMock(LoggerInterface::class);

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $httpResponse = $this->createMock(HttpResponseInterface::class);
        $httpResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['error' => 'Bad request']);

        $exception = new class('Client error') extends \Exception implements ClientExceptionInterface {
            private HttpResponseInterface $response;

            public function setResponse(HttpResponseInterface $response): void
            {
                $this->response = $response;
            }

            public function getResponse(): HttpResponseInterface
            {
                return $this->response;
            }
        };
        $exception->setResponse($httpResponse);

        $logger->expects($this->once())
            ->method('debug')
            ->with('Client error', ['error' => 'Bad request']);

        $platform->expects($this->once())
            ->method('invoke')
            ->willThrowException($exception);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Client error');

        $agent = new Agent($platform, 'gpt-4', logger: $logger);
        $agent->call($messages);
    }

    public function testCallHandlesClientExceptionWithEmptyMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $httpResponse = $this->createMock(HttpResponseInterface::class);
        $httpResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn([]);

        $exception = new class('') extends \Exception implements ClientExceptionInterface {
            private HttpResponseInterface $response;

            public function setResponse(HttpResponseInterface $response): void
            {
                $this->response = $response;
            }

            public function getResponse(): HttpResponseInterface
            {
                return $this->response;
            }
        };
        $exception->setResponse($httpResponse);

        $platform->expects($this->once())
            ->method('invoke')
            ->willThrowException($exception);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request to model or platform');

        $agent = new Agent($platform, 'gpt-4');
        $agent->call($messages);
    }

    public function testCallHandlesHttpException()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

        $exception = $this->createMock(HttpExceptionInterface::class);

        $platform->expects($this->once())
            ->method('invoke')
            ->willThrowException($exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to request model');

        $agent = new Agent($platform, 'gpt-4');
        $agent->call($messages);
    }

    public function testCallPassesOptionsToInvoke()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $options = ['temperature' => 0.7, 'max_tokens' => 100];
        $result = $this->createMock(ResultInterface::class);

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('getModelCatalog')
            ->willReturn(new DynamicModelCatalog());

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
