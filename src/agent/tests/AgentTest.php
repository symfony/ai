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
use PHPUnit\Framework\Attributes\UsesClass;
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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

#[CoversClass(Agent::class)]
#[UsesClass(Input::class)]
#[UsesClass(Output::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(Text::class)]
#[UsesClass(Audio::class)]
#[UsesClass(Image::class)]
#[Small]
final class AgentTest extends TestCase
{
    public function testConstructorInitializesWithDefaults()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);

        $agent = new Agent($platform, $model);

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testConstructorInitializesWithProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $outputProcessor = $this->createMock(OutputProcessorInterface::class);

        $agent = new Agent($platform, $model, [$inputProcessor], [$outputProcessor]);

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testConstructorSetsAgentOnAgentAwareProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);

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

        $agent = new Agent($platform, $model, [$agentAwareProcessor]);

        $this->assertSame($agent, $agentAwareProcessor->agent);
    }

    public function testConstructorThrowsExceptionForInvalidInputProcessor()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $invalidProcessor = new \stdClass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Processor "stdClass" must implement "'.InputProcessorInterface::class.'".');

        /* @phpstan-ignore-next-line */
        new Agent($platform, $model, [$invalidProcessor]);
    }

    public function testConstructorThrowsExceptionForInvalidOutputProcessor()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $invalidProcessor = new \stdClass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Processor "stdClass" must implement "'.OutputProcessorInterface::class.'".');

        /* @phpstan-ignore-next-line */
        new Agent($platform, $model, [], [$invalidProcessor]);
    }

    public function testAgentExposesHisModel()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);

        $agent = new Agent($platform, $model);

        $this->assertSame($model, $agent->getModel());
    }

    public function testCallProcessesInputThroughProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $result = $this->createMock(ResultInterface::class);

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $inputProcessor->expects($this->once())
            ->method('processInput')
            ->with($this->isInstanceOf(Input::class));

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with($model, $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, $model, [$inputProcessor]);
        $actualResult = $agent->call($messages);

        $this->assertSame($result, $actualResult);
    }

    public function testCallProcessesOutputThroughProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $result = $this->createMock(ResultInterface::class);

        $outputProcessor = $this->createMock(OutputProcessorInterface::class);
        $outputProcessor->expects($this->once())
            ->method('processOutput')
            ->with($this->isInstanceOf(Output::class));

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with($model, $messages, [])
            ->willReturn($response);

        $agent = new Agent($platform, $model, [], [$outputProcessor]);
        $actualResult = $agent->call($messages);

        $this->assertSame($result, $actualResult);
    }

    public function testCallThrowsExceptionForAudioInputWithoutSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Audio('audio-data', 'audio/mp3')));

        $model->expects($this->once())
            ->method('supports')
            ->with(Capability::INPUT_AUDIO)
            ->willReturn(false);

        $this->expectException(MissingModelSupportException::class);

        $agent = new Agent($platform, $model);
        $agent->call($messages);
    }

    public function testCallThrowsExceptionForImageInputWithoutSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Image('image-data', 'image/png')));

        $model->expects($this->once())
            ->method('supports')
            ->with(Capability::INPUT_IMAGE)
            ->willReturn(false);

        $this->expectException(MissingModelSupportException::class);

        $agent = new Agent($platform, $model);
        $agent->call($messages);
    }

    public function testCallAllowsAudioInputWithSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Audio('audio-data', 'audio/mp3')));
        $result = $this->createMock(ResultInterface::class);

        $model->expects($this->once())
            ->method('supports')
            ->with(Capability::INPUT_AUDIO)
            ->willReturn(true);

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($response);

        $agent = new Agent($platform, $model);
        $actualResult = $agent->call($messages);

        $this->assertSame($result, $actualResult);
    }

    public function testCallAllowsImageInputWithSupport()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Image('image-data', 'image/png')));
        $result = $this->createMock(ResultInterface::class);
        $model->expects($this->once())
            ->method('supports')
            ->with(Capability::INPUT_IMAGE)
            ->willReturn(true);

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($response);

        $agent = new Agent($platform, $model);
        $actualResult = $agent->call($messages);

        $this->assertSame($result, $actualResult);
    }

    public function testCallHandlesClientException()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $logger = $this->createMock(LoggerInterface::class);

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

        $agent = new Agent($platform, $model, [], [], $logger);
        $agent->call($messages);
    }

    public function testCallHandlesClientExceptionWithEmptyMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));

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

        $agent = new Agent($platform, $model);
        $agent->call($messages);
    }

    public function testCallHandlesHttpException()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));

        $exception = $this->createMock(HttpExceptionInterface::class);

        $platform->expects($this->once())
            ->method('invoke')
            ->willThrowException($exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to request model');

        $agent = new Agent($platform, $model);
        $agent->call($messages);
    }

    public function testCallPassesOptionsToInvoke()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $messages = new MessageBag(new UserMessage(new Text('Hello')));
        $options = ['temperature' => 0.7, 'max_tokens' => 100];
        $result = $this->createMock(ResultInterface::class);

        $rawResult = $this->createMock(RawResultInterface::class);
        $response = new ResultPromise(fn () => $result, $rawResult, []);

        $platform->expects($this->once())
            ->method('invoke')
            ->with($model, $messages, $options)
            ->willReturn($response);

        $agent = new Agent($platform, $model);
        $actualResult = $agent->call($messages, $options);

        $this->assertSame($result, $actualResult);
    }

    public function testConstructorAcceptsTraversableProcessors()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $outputProcessor = $this->createMock(OutputProcessorInterface::class);

        $inputProcessors = new \ArrayIterator([$inputProcessor]);
        $outputProcessors = new \ArrayIterator([$outputProcessor]);

        $agent = new Agent($platform, $model, $inputProcessors, $outputProcessors);

        $this->assertInstanceOf(AgentInterface::class, $agent);
    }

    public function testGetSystemMessageReturnsExpectedValue()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);
        $expectedSystemMessage = 'System prompt here';

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $outputProcessor = $this->createMock(OutputProcessorInterface::class);

        $inputProcessor->expects($this->once())
            ->method('processInput')
            ->willReturnCallback(function (Input $input) use ($expectedSystemMessage) {
                $input->messages->add(Message::forSystem($expectedSystemMessage));
            });

        $agent = new Agent($platform, $model, new \ArrayIterator([$inputProcessor]), new \ArrayIterator([$outputProcessor]));

        $systemMessage = $agent->getSystemMessage();

        $this->assertSame($expectedSystemMessage, $systemMessage);
    }

    public function testGetSystemMessageReturnsNullIfNoSystemMessageSet()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $model = $this->createMock(Model::class);

        $inputProcessor = $this->createMock(InputProcessorInterface::class);
        $outputProcessor = $this->createMock(OutputProcessorInterface::class);

        $inputProcessor->expects($this->once())
            ->method('processInput')
            ->willReturnCallback(function (Input $input) {
            });

        $agent = new Agent($platform, $model, new \ArrayIterator([$inputProcessor]), new \ArrayIterator([$outputProcessor]));

        $systemMessage = $agent->getSystemMessage();

        $this->assertNull($systemMessage);
    }
}
