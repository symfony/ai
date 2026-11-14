<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Speech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Speech\SpeechConfiguration;

final class SpeechConfigurationTest extends TestCase
{
    public function testConfigurationCanBeConfigured()
    {
        $speechConfiguration = new SpeechConfiguration('foo');

        $this->assertFalse($speechConfiguration->supportsSpeechToText());
        $this->assertTrue($speechConfiguration->supportsTextToSpeech());
    }

    public function testConfigurationCanReturnTextToSpeechConfiguration()
    {
        $speechConfiguration = new SpeechConfiguration(
            'foo',
            ['foo' => 'bar'],
        );

        $this->assertSame(['foo' => 'bar'], $speechConfiguration->getTextToSpeechOptions());
    }

    public function testConfigurationCanReturnSpeechToTextConfiguration()
    {
        $speechConfiguration = new SpeechConfiguration(
            null,
            [],
            'foo',
            ['foo' => 'bar'],
        );

        $this->assertSame(['foo' => 'bar'], $speechConfiguration->getSpeechToTextOptions());
    }

    public function testConfigurationSupportsBothSttAndTts()
    {
        $speechConfiguration = new SpeechConfiguration(
            'tts-model',
            [],
            'stt-model',
        );

        $this->assertTrue($speechConfiguration->supportsTextToSpeech());
        $this->assertTrue($speechConfiguration->supportsSpeechToText());
        $this->assertSame('tts-model', $speechConfiguration->getTextToSpeechModel());
        $this->assertSame('stt-model', $speechConfiguration->getSpeechToTextModel());
    }

    public function testGetTextToSpeechModelReturnsNullWhenNotConfigured()
    {
        $speechConfiguration = new SpeechConfiguration();

        $this->assertNull($speechConfiguration->getTextToSpeechModel());
        $this->assertFalse($speechConfiguration->supportsTextToSpeech());
    }

    public function testGetSpeechToTextModelReturnsNullWhenNotConfigured()
    {
        $speechConfiguration = new SpeechConfiguration();

        $this->assertNull($speechConfiguration->getSpeechToTextModel());
        $this->assertFalse($speechConfiguration->supportsSpeechToText());
    }

    public function testDefaultOptionsAreEmpty()
    {
        $speechConfiguration = new SpeechConfiguration('foo');

        $this->assertSame([], $speechConfiguration->getTextToSpeechOptions());
        $this->assertSame([], $speechConfiguration->getSpeechToTextOptions());
    }
}
