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
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ToolCallMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new ToolCallMessage(new ToolCall('', '', []), ''), context: [
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

    /**
     * @return iterable<array{0: ToolCallMessage, 1: array}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'scalar' => [
            new ToolCallMessage(
                new ToolCall('id1', 'name1', ['foo' => 'bar']),
                'true',
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
                '{"structured":"response"}',
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
                '["foo","bar"]',
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
                '{"structured":"response"}',
            ),
            [[
                'functionResponse' => [
                    'name' => 'name1',
                    'response' => ['result' => ['structured' => 'response']],
                ],
            ]],
        ];
    }
}
