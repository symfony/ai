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
use Symfony\AI\Agent\SpeechAgent;
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
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechAwareInterface;
use Symfony\AI\Platform\Speech\SpeechConfiguration;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechAgentTest extends TestCase
{
    public function testCallDelegatesToInnerAgent()
    {
        $expectedResult = new TextResult('hello');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn($expectedResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $agent = new SpeechAgent($innerAgent, $platform, new SpeechConfiguration([]));

        $result = $agent->call(new MessageBag(Message::ofUser('Hello')));

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
            ->willReturn(new TextResult('response'));

        $configuration = new SpeechConfiguration([
            'stt_model' => 'whisper-1',
        ]);

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $platform, $configuration);
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
            ->willReturn(new TextResult('response'));

        $configuration = new SpeechConfiguration([
            'stt_model' => 'whisper-1',
        ]);

        $agent = new SpeechAgent($innerAgent, $platform, $configuration);
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
            ->willReturn(new TextResult('response'));

        $configuration = new SpeechConfiguration([
            'stt_model' => 'whisper-1',
        ]);

        $agent = new SpeechAgent($innerAgent, $platform, $configuration);
        $result = $agent->call(new MessageBag());

        $this->assertSame('response', $result->getContent());
    }

    public function testCallAttachesSpeechToResult()
    {
        $ttsResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-binary')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($ttsResult);

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn(new TextResult('hello'));

        $configuration = new SpeechConfiguration([
            'tts_model' => 'eleven_multilingual_v2',
        ]);

        $agent = new SpeechAgent($innerAgent, $platform, $configuration);
        $result = $agent->call(new MessageBag(Message::ofUser('Say hello')));

        $this->assertInstanceOf(SpeechAwareInterface::class, $result);
        $this->assertInstanceOf(Speech::class, $result->getSpeech());
        $this->assertSame('audio-binary', $result->getSpeech()->asBinary());
    }

    public function testCallSkipsTtsForNonSpeechAwareResult()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('call')
            ->willReturn(new VectorResult(new Vector([0.1, 0.2, 0.3])));

        $configuration = new SpeechConfiguration([
            'tts_model' => 'eleven_multilingual_v2',
        ]);

        $agent = new SpeechAgent($innerAgent, $platform, $configuration);
        $result = $agent->call(new MessageBag(Message::ofUser('Embed this')));

        $this->assertInstanceOf(VectorResult::class, $result);
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
            ->willReturn(new TextResult('LLM response'));

        $configuration = new SpeechConfiguration([
            'stt_model' => 'whisper-1',
            'tts_model' => 'eleven_multilingual_v2',
        ]);

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../fixtures/audio.mp3')),
        );

        $agent = new SpeechAgent($innerAgent, $platform, $configuration);
        $result = $agent->call($messageBag);

        $this->assertInstanceOf(SpeechAwareInterface::class, $result);
        $this->assertSame('LLM response', $result->getContent());
        $this->assertSame('audio-binary', $result->getSpeech()->asBinary());
    }

    public function testGetNameDelegatesToInnerAgent()
    {
        $innerAgent = $this->createMock(AgentInterface::class);
        $innerAgent->expects($this->once())
            ->method('getName')
            ->willReturn('my-agent');

        $platform = $this->createMock(PlatformInterface::class);

        $agent = new SpeechAgent($innerAgent, $platform, new SpeechConfiguration([]));

        $this->assertSame('my-agent', $agent->getName());
    }
}
