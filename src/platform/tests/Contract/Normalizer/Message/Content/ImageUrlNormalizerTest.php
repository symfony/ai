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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ImageUrlNormalizer;
use Symfony\AI\Platform\Message\Content\ImageUrl;

#[CoversClass(ImageUrlNormalizer::class)]
#[UsesClass(ImageUrl::class)]
final class ImageUrlNormalizerTest extends TestCase
{
    private ImageUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ImageUrlNormalizer();
    }

    #[Test]
    public function supportsNormalization(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new ImageUrl('https://example.com/image.jpg')));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        self::assertSame([ImageUrl::class => true], $this->normalizer->getSupportedTypes(null));
    }

    #[Test]
    public function normalize(): void
    {
        $imageUrl = new ImageUrl('https://example.com/image.jpg');

        $expected = [
            'type' => 'image_url',
            'image_url' => ['url' => 'https://example.com/image.jpg'],
        ];

        self::assertSame($expected, $this->normalizer->normalize($imageUrl));
    }
}
