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
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsSpeechProvider;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Speech\SpeechAwarePlatform;
use Symfony\AI\Platform\Speech\SpeechConfiguration;

final class ElevenLabsSpeechProviderTest extends TestCase
{
    public function testProviderCannotSupportOnWrongModel()
    {
        $model = new ElevenLabs('foo');

        $rawResult = $this->createMock(RawResultInterface::class);
        $resultConverter = $this->createMock(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $modelCatalog = $this->createMock(ModelCatalogInterface::class);
        $modelCatalog->expects($this->once())->method('getModel')->willReturn($model);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('getModelCatalog')->willReturn($modelCatalog);

        $speechAwarePlatform = new SpeechAwarePlatform($platform, new SpeechConfiguration(ttsModel: 'foo'));

        $speechListener = new ElevenLabsSpeechProvider($speechAwarePlatform);

        $this->assertFalse($speechListener->support($deferredResult, []));
    }

    public function testProviderCanSupportOnValidModel()
    {
        $model = new ElevenLabs('foo', [
            Capability::TEXT_TO_SPEECH,
        ]);

        $rawResult = $this->createMock(RawResultInterface::class);
        $resultConverter = $this->createMock(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $modelCatalog = $this->createMock(ModelCatalogInterface::class);
        $modelCatalog->expects($this->once())->method('getModel')->willReturn($model);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('getModelCatalog')->willReturn($modelCatalog);

        $speechAwarePlatform = new SpeechAwarePlatform($platform, new SpeechConfiguration(ttsModel: 'foo'));

        $speechListener = new ElevenLabsSpeechProvider($speechAwarePlatform);

        $this->assertTrue($speechListener->support($deferredResult, []));
    }

    public function testProviderCanGenerate()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $secondResultConverter = $this->createMock(ResultConverterInterface::class);
        $secondResultConverter->expects($this->never())->method('convert');

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $speechResult = new DeferredResult($secondResultConverter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($speechResult);

        $speechAwarePlatform = new SpeechAwarePlatform($platform, new SpeechConfiguration(ttsModel: 'foo', ttsVoice: 'bar'));

        $speechListener = new ElevenLabsSpeechProvider($speechAwarePlatform);

        $speech = $speechListener->generate($deferredResult, []);

        $this->assertSame('elevenlabs', $speech->getIdentifier());
    }
}
