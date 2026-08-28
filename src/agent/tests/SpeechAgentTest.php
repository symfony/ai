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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Agent\SpeechAgent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

final class SpeechAgentTest extends TestCase
{
    public function testCallDelegatesToInnerAgent()
    {
        $expectedResult = new TextResult('hello');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution($expectedResult));

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration(), $platform, $platform);

        $result = $agent->call(new MessageBag(Message::ofUser('Hello')))->getResult();

        $this->assertSame($expectedResult, $result);
    }

    public function testCallTranscribesAudioInput()
    {
        $sttResult = new DeferredResult(new PlainConverter(new TextResult('transcribed text')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($sttResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->with($this->callback(static function (MessageBag $messages): bool {
                $latestUser = $messages->latestAs(Role::User);

                return [new Text('transcribed text')] == $latestUser->getContent();
            }))
            ->willReturn($this->execution(new TextResult('response')));

        $configuration = new SpeechConfiguration(sttModel: 'whisper-1');

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call($messageBag);

        $this->assertSame('response', $result->getContent());
    }

    public function testCallSkipsTranscriptionWhenNoAudio()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('response')));

        $configuration = new SpeechConfiguration(sttModel: 'whisper-1');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call(new MessageBag(Message::ofUser('Hello text')));

        $this->assertSame('response', $result->getContent());
    }

    public function testCallSkipsTranscriptionWhenNoUserMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('response')));

        $configuration = new SpeechConfiguration(sttModel: 'whisper-1');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call(new MessageBag());

        $this->assertSame('response', $result->getContent());
    }

    public function testCallAttachesSpeechMetadataWhenTtsConfigured()
    {
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-binary')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'eleven_multilingual_v2');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call(new MessageBag(Message::ofUser('Say hello')))->getResult();

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-binary', $result->getContent());
        $this->assertSame('hello', $result->getMetadata()->get('text'));
    }

    public function testCallForwardsInnerProgress()
    {
        $progress = new Progress('tool_call', 'Calling a tool.');
        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->method('call')->willReturn($this->execution(new TextResult('hello'), $progress));

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration());
        $updates = iterator_to_array($agent->call(new MessageBag(Message::ofUser('Say hello'))));

        $this->assertSame($progress, $updates[0]);
        $this->assertInstanceOf(ResultUpdate::class, $updates[1]);
        $this->assertSame('hello', $updates[1]->getResult()->getContent());
    }

    public function testCallReturnsPlainResultWhenTtsNotConfigured()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('hello')));

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration(), $platform, $platform);
        $result = $agent->call(new MessageBag(Message::ofUser('Say hello')))->getResult();

        $this->assertInstanceOf(TextResult::class, $result);
    }

    public function testCallHandlesBothSttAndTts()
    {
        $sttResult = new DeferredResult(new PlainConverter(new TextResult('transcribed text')), new InMemoryRawResult());
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-binary')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($sttResult, $ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('LLM response')));

        $configuration = new SpeechConfiguration(
            ttsModel: 'eleven_multilingual_v2',
            sttModel: 'whisper-1',
        );

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call($messageBag)->getResult();

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-binary', $result->getContent());
        $this->assertSame('LLM response', $result->getMetadata()->get('text'));
    }

    public function testExceptionIsThrownWhenTtsFails()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willThrowException(new RuntimeException('TTS service unavailable.'));

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($this->execution(new TextResult('hello')));

        $configuration = new SpeechConfiguration(ttsModel: 'eleven_multilingual_v2');

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TTS service unavailable.');
        $this->expectExceptionCode(0);
        $agent->call(new MessageBag(Message::ofUser('Say hello')))->getResult();
    }

    public function testCallWithMultipleMessagesWorksCorrectly()
    {
        $sttResult = new DeferredResult(new PlainConverter(new TextResult('what is the weather?')), new InMemoryRawResult());
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-response')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($sttResult, $ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->with($this->callback(static function (MessageBag $messages): bool {
                // Should have 3 messages: old user text, assistant, new transcribed text
                if (3 !== \count($messages)) {
                    return false;
                }

                $latestUser = $messages->latestAs(Role::User);

                return [new Text('what is the weather?')] == $latestUser->getContent();
            }))
            ->willReturn($this->execution(new TextResult('It is sunny')));

        $configuration = new SpeechConfiguration(
            ttsModel: 'tts-1',
            sttModel: 'whisper-1',
        );

        $messageBag = new MessageBag(
            Message::ofUser('Hello'),
            Message::ofAssistant('Hi there!'),
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $configuration, $platform, $platform);
        $result = $agent->call($messageBag)->getResult();

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-response', $result->getContent());
        $this->assertSame('It is sunny', $result->getMetadata()->get('text'));
    }

    public function testGetNameDelegatesToInnerAgent()
    {
        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('getName')
            ->willReturn('my-agent');

        $platform = $this->createMock(PlatformInterface::class);

        $agent = new SpeechAgent($innerAgent, new SpeechConfiguration(), $platform, $platform);

        $this->assertSame('my-agent', $agent->getName());
    }

    private function execution(ResultInterface $result, Progress ...$progress): Execution
    {
        return new Execution(static function () use ($result, $progress): \Generator {
            yield from $progress;
            yield new ResultUpdate($result);
        });
    }
}
