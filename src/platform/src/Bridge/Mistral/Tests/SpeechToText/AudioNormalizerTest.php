<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText\AudioNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;

final class AudioNormalizerTest extends TestCase
{
    public function testSupportsNormalizationForSpeechToTextModel()
    {
        $normalizer = new AudioNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Audio('audio-data', 'audio/mpeg'), context: [
            Contract::CONTEXT_MODEL => new SpeechToText('voxtral-mini-latest'),
        ]));
    }

    public function testDoesNotSupportNormalizationForOtherModels()
    {
        $normalizer = new AudioNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(new Audio('audio-data', 'audio/mpeg'), context: [
            Contract::CONTEXT_MODEL => new Mistral('mistral-large-latest'),
        ]));
    }

    public function testDoesNotSupportNormalizationForOtherContent()
    {
        $normalizer = new AudioNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(new Text('not audio'), context: [
            Contract::CONTEXT_MODEL => new SpeechToText('voxtral-mini-latest'),
        ]));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new AudioNormalizer();

        $this->assertSame([Audio::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $normalizer = new AudioNormalizer();

        $audio = Audio::fromFile(\dirname(__DIR__, 7).'/fixtures/audio.mp3');
        $normalized = $normalizer->normalize($audio, context: [
            Contract::CONTEXT_MODEL => new SpeechToText('voxtral-mini-latest'),
        ]);

        $this->assertSame('voxtral-mini-latest', $normalized['model']);
        $this->assertIsResource($normalized['file']);
    }
}
