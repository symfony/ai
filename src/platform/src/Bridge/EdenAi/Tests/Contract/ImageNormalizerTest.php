<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\Contract\ImageNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Image;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ImageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ImageNormalizer();
        $image = new Image('binary-image-content', 'image/jpeg');

        $this->assertTrue($normalizer->supportsNormalization($image, context: [
            Contract::CONTEXT_MODEL => new Ocr('ocr/ocr/google'),
        ]));
        $this->assertTrue($normalizer->supportsNormalization($image, context: [
            Contract::CONTEXT_MODEL => new ImageAnalysis('image/object_detection/google'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization($image, context: [
            Contract::CONTEXT_MODEL => new CompletionsModel('openai/gpt-4o'),
        ]));
    }

    public function testNormalize()
    {
        $normalizer = new ImageNormalizer();
        $image = new Image('binary-image-content', 'image/jpeg');

        $normalized = $normalizer->normalize($image);

        $this->assertSame(['file_data'], array_keys($normalized));
        $this->assertNull($normalized['file_data']['filename']);
        $this->assertSame('image/jpeg', $normalized['file_data']['format']);
        $this->assertSame($image->asBase64(), $normalized['file_data']['data']);
    }
}
