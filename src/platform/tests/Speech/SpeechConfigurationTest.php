<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Speech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Speech\SpeechConfiguration;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechConfigurationTest extends TestCase
{
    public function testConfigurationCanBeConfigured()
    {
        $speechConfiguration = new SpeechConfiguration([
            'tts_model' => 'foo',
        ]);

        $this->assertFalse($speechConfiguration->supportsSpeechToText());
        $this->assertTrue($speechConfiguration->supportsTextToSpeech());
    }

    public function testConfigurationCanReturnTextToSpeechConfiguration()
    {
        $speechConfiguration = new SpeechConfiguration([
            'tts_model' => 'foo',
            'tts_options' => [
                'foo' => 'bar',
            ],
        ]);

        $this->assertSame([
            'foo' => 'bar',
        ], $speechConfiguration->getTextToSpeechOptions());
    }

    public function testConfigurationCanReturnSpeechToTextConfiguration()
    {
        $speechConfiguration = new SpeechConfiguration([
            'stt_model' => 'foo',
            'stt_options' => [
                'foo' => 'bar',
            ],
        ]);

        $this->assertSame([
            'foo' => 'bar',
        ], $speechConfiguration->getSpeechToTextOptions());
    }

    public function testConfigurationCanReturnSpecificKey()
    {
        $speechConfiguration = new SpeechConfiguration([
            'tts_model' => 'foo',
            'tts_options' => [
                'foo' => 'bar',
            ],
        ]);

        $this->assertSame([
            'foo' => 'bar',
        ], $speechConfiguration->getOption('tts_options'));
    }

    public function testConfigurationSupportsBothSttAndTts()
    {
        $speechConfiguration = new SpeechConfiguration([
            'tts_model' => 'tts-model',
            'stt_model' => 'stt-model',
        ]);

        $this->assertTrue($speechConfiguration->supportsTextToSpeech());
        $this->assertTrue($speechConfiguration->supportsSpeechToText());
        $this->assertSame('tts-model', $speechConfiguration->getTextToSpeechModel());
        $this->assertSame('stt-model', $speechConfiguration->getSpeechToTextModel());
    }

    public function testGetTextToSpeechModelReturnsNullWhenNotConfigured()
    {
        $speechConfiguration = new SpeechConfiguration([]);

        $this->assertNull($speechConfiguration->getTextToSpeechModel());
        $this->assertFalse($speechConfiguration->supportsTextToSpeech());
    }

    public function testGetSpeechToTextModelReturnsNullWhenNotConfigured()
    {
        $speechConfiguration = new SpeechConfiguration([]);

        $this->assertNull($speechConfiguration->getSpeechToTextModel());
        $this->assertFalse($speechConfiguration->supportsSpeechToText());
    }

    public function testGetOptionReturnsDefaultWhenKeyNotFound()
    {
        $speechConfiguration = new SpeechConfiguration([]);

        $this->assertNull($speechConfiguration->getOption('non_existent_key'));
        $this->assertSame('default_value', $speechConfiguration->getOption('non_existent_key', 'default_value'));
    }
}
