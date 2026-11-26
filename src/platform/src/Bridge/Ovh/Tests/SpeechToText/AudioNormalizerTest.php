<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\SpeechToText;
use Symfony\AI\Platform\Bridge\Ovh\SpeechToText\AudioNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class AudioNormalizerTest extends TestCase
{
    public function testSupportsNormalizationForSpeechToTextModel()
    {
        $normalizer = new AudioNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Audio('audio-data', 'audio/mpeg'), context: [
            Contract::CONTEXT_MODEL => new SpeechToText('whisper-large-v3'),
        ]));
    }

    public function testDoesNotSupportNormalizationForOtherModels()
    {
        $normalizer = new AudioNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(new Audio('audio-data', 'audio/mpeg'), context: [
            Contract::CONTEXT_MODEL => new CompletionsModel('gpt-oss-120b'),
        ]));
    }

    public function testDoesNotSupportNormalizationForOtherContent()
    {
        $normalizer = new AudioNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(new Text('not audio'), context: [
            Contract::CONTEXT_MODEL => new SpeechToText('whisper-large-v3'),
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

        $normalized = $normalizer->normalize(Audio::fromFile(\dirname(__DIR__, 7).'/fixtures/audio.mp3'), context: [
            Contract::CONTEXT_MODEL => new SpeechToText('whisper-large-v3'),
        ]);

        $this->assertSame('whisper-large-v3', $normalized['model']);
        $this->assertIsResource($normalized['file']);
    }
}
