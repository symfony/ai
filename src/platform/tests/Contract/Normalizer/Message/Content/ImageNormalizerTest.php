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
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ImageNormalizer;
use Symfony\AI\Platform\Message\Content\Image;

final class ImageNormalizerTest extends TestCase
{
    private ImageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ImageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(Image::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Image::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $image = Image::fromDataUrl('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABKklEQVR42mNk+A8AAwMhIv9n+Q==');

        $expected = [
            'type' => 'image_url',
            'image_url' => ['url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABKklEQVR42mNk+A8AAwMhIv9n+Q=='],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($image));
    }
}
