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
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\MultimodalNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal\TextNormalizer;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Serializer;

class MultimodalNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $text = new Text('Foo');

        $serializer = new Serializer(
            [
                new MultimodalNormalizer(),
                new CollectionNormalizer(),
                new TextNormalizer(),
            ],
        );

        $this->assertEquals(
            [
                [
                    'content' => [['type' => 'text', 'text' => 'Foo']],
                ],
                [
                    'content' => [
                        ['type' => 'text', 'text' => 'Foo'],
                        ['type' => 'text', 'text' => 'Foo'],
                    ],
                ],
            ],
            $serializer->normalize([
                $text,
                new Collection($text, $text),
            ], context: [Contract::CONTEXT_MODEL => new Voyage('some-model', [Capability::INPUT_MULTIMODAL])])
        );
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $input, Model $model, bool $expected)
    {
        $normalizer = new MultimodalNormalizer();
        $this->assertEquals(
            $expected,
            $normalizer->supportsNormalization($input, context: [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $text = new Text('Foo');
        $model = new Voyage('some-model', [Capability::INPUT_MULTIMODAL]);

        yield 'array of supported objects' => [
            [
                $text,
                new Collection($text),
            ],
            $model,
            true,
        ];

        yield 'array of unsupported data' => [
            [
                $text,
                'Foo',
            ],
            $model,
            false,
        ];

        yield 'not an array' => [
            'Foo',
            $model,
            false,
        ];

        yield 'unsupported model' => [
            [$text],
            new Gpt('some-model', [Capability::INPUT_MULTIMODAL]),
            false,
        ];

        yield 'non-multimodal model' => [
            [$text],
            new Voyage('some-model', []),
            false,
        ];
    }
}
