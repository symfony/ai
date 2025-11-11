<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Contract\Gpt\Message\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\Content\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

class ImageUrlNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $url = new ImageUrl('https://example.com/image.jpg');
        $actual = (new ImageUrlNormalizer())->normalize($url, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);

        $this->assertEquals([
            'type' => 'input_image',
            'image_url' => $url->getUrl(),
        ], $actual);
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new ImageUrlNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $url = new ImageUrl('https://example.com/image.jpg');
        $gpt = new Gpt('o3', [Capability::INPUT_IMAGE]);

        yield 'supported' => [$url, $gpt, true];

        yield 'unsupported model' => [$url, new Gemini('foo', [Capability::INPUT_IMAGE]), false];

        yield 'model lacks image input capability' => [$url, new Gpt('o3'), false];

        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
