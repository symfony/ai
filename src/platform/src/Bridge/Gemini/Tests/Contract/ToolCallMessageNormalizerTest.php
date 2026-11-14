<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Contract\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ToolCallMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new ToolCallMessage(new ToolCall('', '', []), new Text('')), null, [
            Contract::CONTEXT_MODEL => new Gemini('gemini-2.0-flash'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a tool call'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new ToolCallMessageNormalizer();

        $expected = [
            ToolCallMessage::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    /**
     * @param array{functionResponse: array{name: string, response: array{result: mixed}, id?: string}}[] $expected
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(ToolCallMessage $message, array $expected)
    {
        $normalizer = new ToolCallMessageNormalizer();

        $normalized = $normalizer->normalize($message);

        $this->assertEquals($expected, $normalized);
    }

    public function testNormalizeRejectsUnsupportedContentPart()
    {
        $normalizer = new ToolCallMessageNormalizer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported tool result content part of type "Symfony\\AI\\Platform\\Message\\Content\\Audio".');

        $normalizer->normalize(new ToolCallMessage(
            new ToolCall('id1', 'transcription', []),
            new Text('Here is the recording'), new Audio('binary', 'audio/mpeg'),
        ));
    }

    /**
     * @return iterable<array{0: ToolCallMessage, 1: array}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'scalar' => [
            new ToolCallMessage(
                new ToolCall('id1', 'name1', ['foo' => 'bar']),
                new Text('true'),
            ),
            [[
                'functionResponse' => [
                    'name' => 'name1',
                    'response' => ['result' => 'true'],
                    'id' => 'id1',
                ],
            ]],
        ];

        yield 'structured response' => [
            new ToolCallMessage(
                new ToolCall('id1', 'name1', ['foo' => 'bar']),
                new Text('{"structured":"response"}'),
            ),
            [[
                'functionResponse' => [
                    'name' => 'name1',
                    'response' => ['result' => ['structured' => 'response']],
                    'id' => 'id1',
                ],
            ]],
        ];

        yield 'list response is wrapped as a Protobuf Struct' => [
            new ToolCallMessage(
                new ToolCall('id1', 'name1', ['foo' => 'bar']),
                new Text('["foo","bar"]'),
            ),
            [[
                'functionResponse' => [
                    'name' => 'name1',
                    'response' => ['result' => ['foo', 'bar']],
                    'id' => 'id1',
                ],
            ]],
        ];

        yield 'empty id is omitted' => [
            new ToolCallMessage(
                new ToolCall('', 'name1', ['foo' => 'bar']),
                new Text('{"structured":"response"}'),
            ),
            [[
                'functionResponse' => [
                    'name' => 'name1',
                    'response' => ['result' => ['structured' => 'response']],
                ],
            ]],
        ];

        yield 'multimodal result adds inline_data parts' => [
            new ToolCallMessage(
                new ToolCall('id1', 'screenshot', []),
                new Text('Here is the screenshot'), new Image('binary', 'image/png'),
            ),
            [
                [
                    'functionResponse' => [
                        'name' => 'screenshot',
                        'response' => ['result' => 'Here is the screenshot'],
                        'id' => 'id1',
                    ],
                ],
                [
                    'inline_data' => [
                        'mime_type' => 'image/png',
                        'data' => base64_encode('binary'),
                    ],
                ],
            ],
        ];
    }
}
