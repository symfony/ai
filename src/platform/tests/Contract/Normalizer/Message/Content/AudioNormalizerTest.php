<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Message\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\AudioNormalizer;
use Symfony\AI\Platform\Message\Content\Audio;

final class AudioNormalizerTest extends TestCase
{
    private AudioNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new AudioNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(Audio::fromFile(\dirname(__DIR__, 7).'/fixtures/audio.mp3')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Audio::class => true], $this->normalizer->getSupportedTypes(null));
    }

    #[DataProvider('provideAudioData')]
    public function testNormalize(string $data, string $format, array $expected)
    {
        $audio = new Audio(base64_decode($data), $format, \dirname(__DIR__, 7).'/fixtures/audio.mp3');

        $this->assertSame($expected, $this->normalizer->normalize($audio));
    }

    public static function provideAudioData(): \Generator
    {
        yield 'mp3 data' => [
            'SUQzBAAAAAAAfVREUkMAAAAMAAADMg==',
            'audio/mpeg',
            [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => 'SUQzBAAAAAAAfVREUkMAAAAMAAADMg==',
                    'format' => 'mp3',
                ],
            ],
        ];

        yield 'wav data' => [
            'UklGRiQAAABXQVZFZm10IBA=',
            'audio/wav',
            [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => 'UklGRiQAAABXQVZFZm10IBA=',
                    'format' => 'wav',
                ],
            ],
        ];
    }
}
