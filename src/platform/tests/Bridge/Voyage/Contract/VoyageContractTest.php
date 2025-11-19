<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Voyage\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\Contract\VoyageContract;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;

final class VoyageContractTest extends TestCase
{
    #[DataProvider('createRequestPayloadProvider')]
    public function testCreateMultimodalRequestPayload(array|ContentInterface $input, array $expected)
    {
        $contract = VoyageContract::create();

        $this->assertEquals($expected, $contract->createRequestPayload(
            new Voyage('some-model', [Capability::INPUT_MULTIMODAL]),
            $input
        ));
    }

    public static function createRequestPayloadProvider(): \Generator
    {
        $text = new Text('Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        $imageUrl = new ImageUrl('https://example.com/image.jpg');

        yield 'single content value' => [
            $text,
            [
                [
                    'content' => [
                        ['type' => 'text', 'text' => $text->getText()],
                    ],
                ],
            ],
        ];

        yield 'single multimodal value' => [
            new Collection($text, $imageUrl),
            [
                [
                    'content' => [
                        ['type' => 'text', 'text' => $text->getText()],
                        ['type' => 'image_url', 'image_url' => $imageUrl->getUrl()],
                    ],
                ],
            ],
        ];

        yield 'multiple multimodal values' => [
            [
                new Collection($text, $imageUrl),
                new Collection($text, $imageUrl),
            ],
            [
                [
                    'content' => [
                        ['type' => 'text', 'text' => $text->getText()],
                        ['type' => 'image_url', 'image_url' => $imageUrl->getUrl()],
                    ],
                ],
                [
                    'content' => [
                        ['type' => 'text', 'text' => $text->getText()],
                        ['type' => 'image_url', 'image_url' => $imageUrl->getUrl()],
                    ],
                ],
            ],
        ];

        yield 'multiple mixed content and multimodal values' => [
            [
                $imageUrl,
                new Collection($text, $imageUrl),
            ],
            [
                [
                    'content' => [
                        ['type' => 'image_url', 'image_url' => $imageUrl->getUrl()],
                    ],
                ],
                [
                    'content' => [
                        ['type' => 'text', 'text' => $text->getText()],
                        ['type' => 'image_url', 'image_url' => $imageUrl->getUrl()],
                    ],
                ],
            ],
        ];
    }
}
