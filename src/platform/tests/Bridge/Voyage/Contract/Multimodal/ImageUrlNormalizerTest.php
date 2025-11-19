<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Voyage\Contract\Multimodal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\CollectionNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Model;

final class ImageUrlNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $urlStr = 'https://example.org/foo.jpg';
        $url = new ImageUrl($urlStr);

        $normalizer = new ImageUrlNormalizer();
        $normalized = $normalizer->normalize($url);

        $this->assertEquals([[
            CollectionNormalizer::KEY_CONTENT => [
                [
                    'type' => 'image_url',
                    'image_url' => $urlStr,
                ]],
        ]], $normalized);
    }

    #[DataProvider('supportsNormalizationDataProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $result)
    {
        $normalizer = new ImageUrlNormalizer();
        $this->assertEquals($result, $normalizer->supportsNormalization($data, context: [
            Contract::CONTEXT_MODEL => $model,
        ]));
    }

    public static function supportsNormalizationDataProvider(): \Generator
    {
        $url = new ImageUrl('https://example.org/foo.jpg');

        yield 'supported' => [$url, new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL]), true];
        yield 'not an image' => [[], new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL]), false];
        yield 'non-multimodal model' => [$url, new Voyage('voyage-3.5'), false];
        yield 'unsupported model' => [$url, new Gpt('gpt-40'), false];
    }
}
