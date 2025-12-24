<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsSpeechPlatform;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

final class ElevenLabsSpeechPlatformTest extends TestCase
{
    public function testPlatformCannotListenWithoutConfiguration()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $resultConverter = $this->createMock(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);

        $speechAwarePlatform = new ElevenLabsSpeechPlatform($platform, [
            'tts_model' => 'foo',
        ]);

        $this->assertNull($speechAwarePlatform->listen($deferredResult, []));
    }

    public function testPlatformCannotGenerateWithoutConfiguration()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $resultConverter = $this->createMock(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);

        $speechAwarePlatform = new ElevenLabsSpeechPlatform($platform, [
            'stt_model' => 'foo',
        ]);

        $this->assertNull($speechAwarePlatform->generate($deferredResult, []));
    }

    public function testListenerCanListenOnArrayInput()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->with('foo')
            ->willReturn(new DeferredResult($resultConverter, $rawResult));

        $speechAwarePlatform = new ElevenLabsSpeechPlatform($platform, [
            'stt_model' => 'foo',
        ]);

        $text = $speechAwarePlatform->listen(['text' => 'foo'], []);

        $this->assertInstanceOf(DeferredResult::class, $text);
        $this->assertSame('foo', $text->asText());
    }

    public function testListenerCanListenOnMessageBag()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->with('foo')->willReturn($deferredResult);

        $speechAwarePlatform = new ElevenLabsSpeechPlatform($platform, [
            'stt_model' => 'foo',
        ]);

        $text = $speechAwarePlatform->listen(new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3')),
        ), []);

        $this->assertInstanceOf(DeferredResult::class, $text);
        $this->assertSame('foo', $text->asText());
    }

    public function testPlatformCanGenerate()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $secondResultConverter = $this->createMock(ResultConverterInterface::class);
        $secondResultConverter->expects($this->never())->method('convert');

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')
            ->willReturn(new DeferredResult($secondResultConverter, $rawResult));

        $speechPlatform = new ElevenLabsSpeechPlatform($platform, [
            'tts_model' => 'foo',
            'tts_voice' => 'foo',
        ]);

        $speech = $speechPlatform->generate($deferredResult, []);

        $this->assertInstanceOf(DeferredResult::class, $speech);
    }
}
