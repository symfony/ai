<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Mistral\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Contract\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\ImageUrl;

final class ImageUrlNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ImageUrlNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new ImageUrl('https://example.com/image.png'), context: [
            Contract::CONTEXT_MODEL => new Mistral('mistral-large-latest'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a image url'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new ImageUrlNormalizer();

        $expected = [
            ImageUrl::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(ImageUrl $file, array $expected)
    {
        $normalizer = new ImageUrlNormalizer();

        $normalized = $normalizer->normalize($file);

        $this->assertEquals($expected, $normalized);
    }

    public static function normalizeDataProvider(): iterable
    {
        yield 'image with url' => [
            new ImageUrl('https://example.com/image.png'),
            [
                'type' => 'image_url',
                'image_url' => 'https://example.com/image.png',
            ],
        ];
    }
}
