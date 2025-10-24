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
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\ImageNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;

final class ImageNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $image = $this->getFixtureImage();

        $normalizer = new ImageNormalizer();
        $normalized = $normalizer->normalize($image);

        $this->assertEquals([[
            CollectionNormalizer::KEY_CONTENT => [
                [
                    'type' => 'image_base64',
                    'image_base64' => 'data:image/jpeg;base64,'.$image->asBase64(),
                ],
            ],
        ]], $normalized);
    }

    #[DataProvider('supportsNormalizationDataProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $result)
    {
        $normalizer = new ImageNormalizer();
        $this->assertEquals($result, $normalizer->supportsNormalization($data, context: [
            Contract::CONTEXT_MODEL => $model,
        ]));
    }

    public static function supportsNormalizationDataProvider(): \Generator
    {
        $image = self::getFixtureImage();

        yield 'supported' => [$image, new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL]), true];
        yield 'not an image' => [[], new Voyage('voyage-multimodal-3', [Capability::INPUT_MULTIMODAL]), false];
        yield 'non-multimodal model' => [$image, new Voyage('voyage-3.5'), false];
        yield 'unsupported model' => [$image, new Gpt('gpt-40'), false];
    }

    private static function getFixtureImage(): Image
    {
        return Image::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg');
    }
}
