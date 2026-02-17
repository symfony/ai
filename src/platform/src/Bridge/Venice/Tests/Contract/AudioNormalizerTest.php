<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\Contract\AudioNormalizer;
use Symfony\AI\Platform\Message\Content\Audio;

final class AudioNormalizerTest extends TestCase
{
    public function testSupportsNormalizationForAudio()
    {
        $normalizer = new AudioNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Audio('audio-data', 'audio/mpeg')));
    }

    public function testDoesNotSupportNormalizationForOtherTypes()
    {
        $normalizer = new AudioNormalizer();

        $this->assertFalse($normalizer->supportsNormalization('a string'));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new AudioNormalizer();

        $this->assertSame([Audio::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalizeMapsMpegToMp3()
    {
        $normalizer = new AudioNormalizer();
        $audio = new Audio('audio-bytes', 'audio/mpeg');

        $result = $normalizer->normalize($audio);

        $this->assertIsArray($result);
        $this->assertSame('input_audio', $result['type']);
        $this->assertSame(base64_encode('audio-bytes'), $result['input_audio']['data']);
        $this->assertSame('mp3', $result['input_audio']['format']);
        $this->assertNull($result['input_audio']['path']);
    }

    public function testNormalizeMapsAudioWavToWav()
    {
        $normalizer = new AudioNormalizer();
        $audio = new Audio('audio-bytes', 'audio/wav');

        $result = $normalizer->normalize($audio);

        $this->assertSame('wav', $result['input_audio']['format']);
    }

    public function testNormalizePreservesOtherFormats()
    {
        $normalizer = new AudioNormalizer();
        $audio = new Audio('audio-bytes', 'audio/flac');

        $result = $normalizer->normalize($audio);

        $this->assertSame('audio/flac', $result['input_audio']['format']);
    }

    public function testNormalizeIncludesPathWhenLoadedFromFile()
    {
        $normalizer = new AudioNormalizer();
        $path = \dirname(__DIR__, 7).'/fixtures/audio.mp3';
        $audio = Audio::fromFile($path);

        $result = $normalizer->normalize($audio);

        $this->assertSame($path, $result['input_audio']['path']);
        $this->assertSame('mp3', $result['input_audio']['format']);
    }
}
