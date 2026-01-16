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
}
