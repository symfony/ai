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
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\TextNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\Component\Serializer\Serializer;

class CollectionNormalizerTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function testNormalize(mixed $data, array $expected)
    {
        $serializer = new Serializer(
            [
                new CollectionNormalizer(),
                new TextNormalizer(),
                new ImageUrlNormalizer(),
            ],
        );

        $actual = $serializer->normalize(
            $data,
            context: [
                Contract::CONTEXT_MODEL => new Voyage('some-model', [Capability::INPUT_MULTIMODAL]),
            ],
        );

        $this->assertEquals($expected, $actual);
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, array $context, bool $expected)
    {
        $normalizer = new CollectionNormalizer();
        $this->assertEquals(
            $expected,
            $normalizer->supportsNormalization($data, context: $context)
        );
    }

    public static function normalizeProvider(): \Generator
    {
        $text = new Text('Lorem ipsum');
        $imageUrl = new ImageUrl('https://example.com/image.jpg');

        yield 'single value' => [
            new Collection($text),
            [
                ['content' => [
                    ['type' => 'text', 'text' => $text->getText()],
                ]],
            ],
        ];

        yield 'multiple values' => [
            new Collection($text, $imageUrl),
            [
                ['content' => [
                    ['type' => 'text', 'text' => $text->getText()],
                    ['type' => 'image_url', 'image_url' => $imageUrl->getUrl()],
                ]],
            ],
        ];
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        yield 'supported object' => [
            new Collection(),
            [
                Contract::CONTEXT_MODEL => new Voyage('some-model', [Capability::INPUT_MULTIMODAL]),
            ],
            true,
        ];
        yield 'unsupported model' => [
            new Collection(),
            [
                Contract::CONTEXT_MODEL => new Gpt('some-model', [Capability::INPUT_MULTIMODAL]),
            ],
            false,
        ];
        yield 'model lacks multimodal capability' => [
            new Collection(),
            [
                Contract::CONTEXT_MODEL => new Voyage('some-model'),
            ],
            false,
        ];
        yield 'unsupported data' => [
            'Foo',
            [
                Contract::CONTEXT_MODEL => new Voyage('some-model', [Capability::INPUT_MULTIMODAL]),
            ],
            false,
        ];
    }
}
