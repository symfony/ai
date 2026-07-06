<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\Contract\ImageNormalizer;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;

final class ImageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ImageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(Image::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg')));
        $this->assertFalse($normalizer->supportsNormalization(new Text('foo')));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new ImageNormalizer();

        $this->assertSame([Image::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $normalizer = new ImageNormalizer();

        $normalized = $normalizer->normalize(Image::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg'));

        $this->assertSame('image_url', $normalized['type']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $normalized['image_url']);
    }
}
