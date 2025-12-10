<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\ElevenLabs;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsSpeechListener;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Speech\SpeechAwarePlatform;
use Symfony\AI\Platform\Speech\SpeechConfiguration;

final class ElevenLabsSpeechListenerTest extends TestCase
{
    public function testListenerCannotSupportOnWrongModel()
    {
        $model = new ElevenLabs('foo');

        $modelCatalog = $this->createMock(ModelCatalogInterface::class);
        $modelCatalog->expects($this->once())->method('getModel')->willReturn($model);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('getModelCatalog')->willReturn($modelCatalog);

        $speechAwarePlatform = new SpeechAwarePlatform($platform, new SpeechConfiguration(sttModel: 'foo'));

        $speechListener = new ElevenLabsSpeechListener($speechAwarePlatform);

        $this->assertFalse($speechListener->support([], []));
    }

    public function testListenerCanSupportOnValidModel()
    {
        $model = new ElevenLabs('foo', [
            Capability::SPEECH_TO_TEXT,
        ]);

        $modelCatalog = $this->createMock(ModelCatalogInterface::class);
        $modelCatalog->expects($this->once())->method('getModel')->willReturn($model);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('getModelCatalog')->willReturn($modelCatalog);

        $speechAwarePlatform = new SpeechAwarePlatform($platform, new SpeechConfiguration(sttModel: 'foo'));

        $speechListener = new ElevenLabsSpeechListener($speechAwarePlatform);

        $this->assertTrue($speechListener->support([], []));
    }

    public function testListenerCanListenOnArrayInput()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->with('foo')->willReturn($deferredResult);

        $speechAwarePlatform = new SpeechAwarePlatform($platform, new SpeechConfiguration(sttModel: 'foo'));

        $speechListener = new ElevenLabsSpeechListener($speechAwarePlatform);

        $text = $speechListener->listen(['text' => 'foo'], []);

        $this->assertInstanceOf(Text::class, $text);
        $this->assertSame('foo', $text->getText());
    }

    public function testListenerCanListenOnMessageBag()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->with('foo')->willReturn($deferredResult);

        $speechAwarePlatform = new SpeechAwarePlatform($platform, new SpeechConfiguration(sttModel: 'foo'));

        $speechListener = new ElevenLabsSpeechListener($speechAwarePlatform);

        $text = $speechListener->listen(new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3')),
        ), []);

        $this->assertInstanceOf(Text::class, $text);
        $this->assertSame('foo', $text->getText());
    }
}
