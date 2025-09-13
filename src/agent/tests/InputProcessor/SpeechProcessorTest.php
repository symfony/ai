<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\InputProcessor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\SpeechProcessor;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
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
use Symfony\AI\Platform\Speech\SpeechConfiguration;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechProcessorTest extends TestCase
{
    public function testProcessorCannotBeTriggeredOnInputWithoutConfiguration()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([]);

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processInput(new Input('foo', new MessageBag()));
    }

    public function testProcessorCannotBeTriggeredOnInputWithoutUserMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([
            'stt_model' => 'foo',
        ]);

        $processor = new SpeechProcessor($platform, $configuration);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No message found for role "User".');
        $this->expectExceptionCode(0);
        $processor->processInput(new Input('foo', new MessageBag()));
    }

    public function testProcessorSkipsInputWhenSttConfiguredButNoAudio()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([
            'stt_model' => 'foo',
        ]);

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processInput(new Input('foo', new MessageBag(
            Message::ofUser('Hello there'),
        )));

        $this->addToAssertionCount(1);
    }

    public function testProcessorCanBeTriggeredOnInputWithAudio()
    {
        $deferredResult = new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($deferredResult);

        $configuration = new SpeechConfiguration([
            'stt_model' => 'foo',
        ]);

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../../fixtures/audio.mp3')),
        );

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processInput(new Input('foo', $messageBag));

        $this->assertEquals([new Text('foo')], $messageBag->latestAs(Role::User)->getContent());
    }

    public function testProcessorCannotBeTriggeredOnOutputWithoutConfiguration()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([]);

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processOutput(new Output('foo', new TextResult('foo'), new MessageBag()));
    }

    public function testProcessorCanBeTriggeredOnOutputWithConfiguration()
    {
        $deferredResult = new DeferredResult(new PlainConverter(new BinaryResult('foo')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($deferredResult);

        $configuration = new SpeechConfiguration([
            'tts_model' => 'foo',
        ]);

        $result = new TextResult('foo');

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processOutput(new Output('foo', $result, new MessageBag()));

        $this->assertInstanceOf(Speech::class, $result->getSpeech());
    }

    public function testProcessorHandlesBothSttAndTtsConfiguration()
    {
        $sttDeferredResult = new DeferredResult(new PlainConverter(new TextResult('transcribed text')), new InMemoryRawResult());
        $ttsDeferredResult = new DeferredResult(new PlainConverter(new BinaryResult('audio-binary')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls($sttDeferredResult, $ttsDeferredResult);

        $configuration = new SpeechConfiguration([
            'stt_model' => 'stt-model',
            'tts_model' => 'tts-model',
        ]);

        $messageBag = new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__).'/../../../fixtures/audio.mp3')),
        );

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processInput(new Input('foo', $messageBag));

        $this->assertEquals([new Text('transcribed text')], $messageBag->latestAs(Role::User)->getContent());

        $result = new TextResult('response text');
        $processor->processOutput(new Output('foo', $result, $messageBag));

        $this->assertInstanceOf(Speech::class, $result->getSpeech());
        $this->assertSame('audio-binary', $result->getSpeech()->asBinary());
    }

    public function testProcessorOutputSkipsNonSpeechAwareResult()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([
            'tts_model' => 'foo',
        ]);

        $result = new VectorResult(new Vector([0.1, 0.2, 0.3]));

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processOutput(new Output('foo', $result, new MessageBag()));

        $this->addToAssertionCount(1);
    }

    public function testProcessorOutputVerifiesSpeechBinaryContent()
    {
        $binaryContent = 'audio-binary-content-here';
        $deferredResult = new DeferredResult(new PlainConverter(new BinaryResult($binaryContent)), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($deferredResult);

        $configuration = new SpeechConfiguration([
            'tts_model' => 'foo',
        ]);

        $result = new TextResult('hello');

        $processor = new SpeechProcessor($platform, $configuration);
        $processor->processOutput(new Output('foo', $result, new MessageBag()));

        $speech = $result->getSpeech();
        $this->assertNotNull($speech);
        $this->assertSame($binaryContent, $speech->asBinary());
        $this->assertSame(base64_encode($binaryContent), $speech->asBase64());
    }
}
