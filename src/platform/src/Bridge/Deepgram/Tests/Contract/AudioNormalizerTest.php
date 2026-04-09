<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\Contract\AudioNormalizer;
use Symfony\AI\Platform\Message\Content\Audio;

final class AudioNormalizerTest extends TestCase
{
    public function testSupportsOnlyAudio()
    {
        $normalizer = new AudioNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Audio('bytes', 'audio/mpeg')));
        $this->assertFalse($normalizer->supportsNormalization('a string'));
        $this->assertFalse($normalizer->supportsNormalization(null));
    }

    public function testSupportedTypes()
    {
        $normalizer = new AudioNormalizer();

        $this->assertSame([Audio::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalizeMp3()
    {
        $audio = new Audio('binary', 'audio/mpeg', '/path/audio.mp3');

        $payload = (new AudioNormalizer())->normalize($audio);

        $this->assertSame('input_audio', $payload['type']);
        $inputAudio = $payload['input_audio'];
        $this->assertSame(base64_encode('binary'), $inputAudio['data']);
        $this->assertSame('/path/audio.mp3', $inputAudio['path']);
        $this->assertSame('mp3', $inputAudio['format']);
    }

    public function testNormalizeWav()
    {
        $audio = new Audio('binary', 'audio/wav');

        $payload = (new AudioNormalizer())->normalize($audio);

        $inputAudio = $payload['input_audio'];
        $this->assertSame('wav', $inputAudio['format']);
        $this->assertNull($inputAudio['path']);
    }

    public function testNormalizePassesThroughUnknownFormat()
    {
        $audio = new Audio('binary', 'audio/x-custom');

        $payload = (new AudioNormalizer())->normalize($audio);

        $this->assertSame('audio/x-custom', $payload['input_audio']['format']);
    }

    public function testNormalizeFromFile()
    {
        $fixture = \dirname(__DIR__).'/Fixtures/audio.mp3';

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile($fixture));

        $inputAudio = $payload['input_audio'];
        $this->assertSame('mp3', $inputAudio['format']);
        $this->assertSame($fixture, $inputAudio['path']);
        $this->assertSame(base64_encode((string) file_get_contents($fixture)), $inputAudio['data']);
    }
}
