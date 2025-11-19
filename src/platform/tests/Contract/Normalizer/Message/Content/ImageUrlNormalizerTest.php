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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ImageUrlNormalizer;
use Symfony\AI\Platform\Message\Content\ImageUrl;

final class ImageUrlNormalizerTest extends TestCase
{
    private ImageUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ImageUrlNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ImageUrl('https://example.com/image.jpg')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([ImageUrl::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $imageUrl = new ImageUrl('https://example.com/image.jpg');

        $expected = [
            'type' => 'image_url',
            'image_url' => ['url' => 'https://example.com/image.jpg'],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($imageUrl));
    }
}
