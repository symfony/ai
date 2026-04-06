<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Contract\ToolCallNormalizer;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallNormalizerTest extends TestCase
{
    private ToolCallNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ToolCallNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ToolCall('id1', 'function1'), context: [
            Contract::CONTEXT_MODEL => new Ollama('llama3.2'),
        ]));
        $this->assertFalse($this->normalizer->supportsNormalization(new ToolCall('id1', 'function1'), context: [
            Contract::CONTEXT_MODEL => new Model('any-model'),
        ]));
        $this->assertFalse($this->normalizer->supportsNormalization('not a tool call'));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([ToolCall::class => true], $this->normalizer->getSupportedTypes(null));
    }

    /**
     * @param array{type: 'function', function: array{name: string, arguments: mixed}} $expectedOutput
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(ToolCall $toolCall, array $expectedOutput)
    {
        $normalized = $this->normalizer->normalize($toolCall);

        $this->assertEquals($expectedOutput, $normalized);
    }

    /**
     * @return iterable<string, array{ToolCall, array{type: 'function', function: array{name: string, arguments: mixed}}}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'tool call with arguments' => [
            new ToolCall('id1', 'function1', ['param' => 'value']),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'function1',
                    'arguments' => ['param' => 'value'],
                ],
            ],
        ];

        yield 'tool call with empty arguments' => [
            new ToolCall('id1', 'function1', []),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'function1',
                    'arguments' => new \stdClass(),
                ],
            ],
        ];
    }
}
