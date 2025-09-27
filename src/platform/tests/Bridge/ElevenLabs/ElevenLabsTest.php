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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Capability;

final class ElevenLabsTest extends TestCase
{
    public function testSpeechToTextModelHasCorrectCapabilities()
    {
        $model = new ElevenLabs(ElevenLabs::SCRIBE_V1);

        $this->assertTrue($model->supports(Capability::INPUT_AUDIO));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertTrue($model->supports(Capability::SPEECH_TO_TEXT));
        $this->assertFalse($model->supports(Capability::INPUT_TEXT));
        $this->assertFalse($model->supports(Capability::OUTPUT_AUDIO));
        $this->assertFalse($model->supports(Capability::TEXT_TO_SPEECH));
    }

    public function testSpeechToTextExperimentalModelHasCorrectCapabilities()
    {
        $model = new ElevenLabs(ElevenLabs::SCRIBE_V1_EXPERIMENTAL);

        $this->assertTrue($model->supports(Capability::INPUT_AUDIO));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertTrue($model->supports(Capability::SPEECH_TO_TEXT));
        $this->assertFalse($model->supports(Capability::INPUT_TEXT));
        $this->assertFalse($model->supports(Capability::OUTPUT_AUDIO));
        $this->assertFalse($model->supports(Capability::TEXT_TO_SPEECH));
    }

    public function testTextToSpeechModelHasCorrectCapabilities()
    {
        $model = new ElevenLabs(ElevenLabs::ELEVEN_MULTILINGUAL_V2);

        $this->assertTrue($model->supports(Capability::INPUT_TEXT));
        $this->assertTrue($model->supports(Capability::OUTPUT_AUDIO));
        $this->assertTrue($model->supports(Capability::TEXT_TO_SPEECH));
        $this->assertFalse($model->supports(Capability::INPUT_AUDIO));
        $this->assertFalse($model->supports(Capability::OUTPUT_TEXT));
        $this->assertFalse($model->supports(Capability::SPEECH_TO_TEXT));
    }

    public function testGetCapabilitiesReturnsSpeechToTextCapabilities()
    {
        $model = new ElevenLabs(ElevenLabs::SCRIBE_V1);

        $capabilities = $model->getCapabilities();

        $this->assertCount(3, $capabilities);
        $this->assertContains(Capability::INPUT_AUDIO, $capabilities);
        $this->assertContains(Capability::OUTPUT_TEXT, $capabilities);
        $this->assertContains(Capability::SPEECH_TO_TEXT, $capabilities);
    }

    public function testGetCapabilitiesReturnsTextToSpeechCapabilities()
    {
        $model = new ElevenLabs(ElevenLabs::ELEVEN_V3);

        $capabilities = $model->getCapabilities();

        $this->assertCount(3, $capabilities);
        $this->assertContains(Capability::INPUT_TEXT, $capabilities);
        $this->assertContains(Capability::OUTPUT_AUDIO, $capabilities);
        $this->assertContains(Capability::TEXT_TO_SPEECH, $capabilities);
    }

    public function testModelNameIsCorrectlySet()
    {
        $model = new ElevenLabs(ElevenLabs::SCRIBE_V1);

        $this->assertSame(ElevenLabs::SCRIBE_V1, $model->getName());
    }

    public function testModelOptionsAreCorrectlySet()
    {
        $options = ['voice' => 'test-voice', 'speed' => 1.2];
        $model = new ElevenLabs(ElevenLabs::ELEVEN_MULTILINGUAL_V2, $options);

        $this->assertSame($options, $model->getOptions());
    }

    #[DataProvider('speechToTextModelProvider')]
    public function testAllSpeechToTextModelsHaveCorrectCapabilities(string $modelName)
    {
        $model = new ElevenLabs($modelName);

        $this->assertTrue($model->supports(Capability::SPEECH_TO_TEXT));
        $this->assertTrue($model->supports(Capability::INPUT_AUDIO));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
    }

    #[DataProvider('textToSpeechModelProvider')]
    public function testAllTextToSpeechModelsHaveCorrectCapabilities(string $modelName)
    {
        $model = new ElevenLabs($modelName);

        $this->assertTrue($model->supports(Capability::TEXT_TO_SPEECH));
        $this->assertTrue($model->supports(Capability::INPUT_TEXT));
        $this->assertTrue($model->supports(Capability::OUTPUT_AUDIO));
    }

    public static function speechToTextModelProvider(): iterable
    {
        yield [ElevenLabs::SCRIBE_V1];
        yield [ElevenLabs::SCRIBE_V1_EXPERIMENTAL];
    }

    public static function textToSpeechModelProvider(): iterable
    {
        yield [ElevenLabs::ELEVEN_V3];
        yield [ElevenLabs::ELEVEN_TTV_V3];
        yield [ElevenLabs::ELEVEN_MULTILINGUAL_V2];
        yield [ElevenLabs::ELEVEN_FLASH_V250];
        yield [ElevenLabs::ELEVEN_FLASH_V2];
        yield [ElevenLabs::ELEVEN_TURBO_V2_5];
        yield [ElevenLabs::ELEVEN_TURBO_V2];
        yield [ElevenLabs::ELEVEN_MULTILINGUAL_STS_V2];
        yield [ElevenLabs::ELEVEN_MULTILINGUAL_TTV_V2];
        yield [ElevenLabs::ELEVEN_ENGLISH_STS_V2];
    }
}
