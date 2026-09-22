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
use Symfony\AI\Platform\Bridge\EdenAi\Contract\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\ImageUrl;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ImageUrlNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ImageUrlNormalizer();
        $imageUrl = new ImageUrl('https://example.com/scan.png');

        $this->assertTrue($normalizer->supportsNormalization($imageUrl, context: [
            Contract::CONTEXT_MODEL => new Ocr('ocr/ocr/google'),
        ]));
        $this->assertTrue($normalizer->supportsNormalization($imageUrl, context: [
            Contract::CONTEXT_MODEL => new DocumentParser('ocr/identity_parser/amazon'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization($imageUrl, context: [
            Contract::CONTEXT_MODEL => new CompletionsModel('openai/gpt-4o'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not an image url'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new ImageUrlNormalizer();

        $this->assertSame([ImageUrl::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $normalizer = new ImageUrlNormalizer();

        $this->assertSame(
            ['file' => 'https://example.com/scan.png'],
            $normalizer->normalize(new ImageUrl('https://example.com/scan.png')),
        );
    }
}
