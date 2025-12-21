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
use Symfony\AI\Platform\Bridge\Ollama\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;

final class AssistantMessageNormalizerTest extends TestCase
{
    private AssistantMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new AssistantMessageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new AssistantMessage('Hello'), context: [
            Contract::CONTEXT_MODEL => new Ollama('llama3.2'),
        ]));
        $this->assertFalse($this->normalizer->supportsNormalization(new AssistantMessage('Hello'), context: [
            Contract::CONTEXT_MODEL => new Model('any-model'),
        ]));
        $this->assertFalse($this->normalizer->supportsNormalization('not an assistant message'));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([AssistantMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    /**
     * @param array{role: Role::Assistant, content: string, tool_calls: list<array{type: 'function', function: array{name: string, arguments: array<string, mixed>}}>} $expectedOutput
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(AssistantMessage $message, array $expectedOutput)
    {
        $normalized = $this->normalizer->normalize($message);

        $this->assertEquals($expectedOutput, $normalized);
    }

    /**
     * @return iterable<string, array{AssistantMessage, array{role: Role::Assistant, tool_calls: list<array{type: string, function: array{name: string, arguments: mixed}}>}}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'assistant message without tool calls' => [
            new AssistantMessage('Hello'),
            [
                'role' => Role::Assistant,
                'content' => 'Hello',
                'tool_calls' => [],
            ],
        ];

        yield 'assistant message with tool calls' => [
            new AssistantMessage(toolCalls: [new ToolCall('id1', 'function1', ['param' => 'value'])]),
            [
                'role' => Role::Assistant,
                'content' => '',
                'tool_calls' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'function1',
                            'arguments' => ['param' => 'value'],
                        ],
                    ],
                ],
            ],
        ];

        yield 'assistant message with empty arguments' => [
            new AssistantMessage(toolCalls: [new ToolCall('id1', 'function1', [])]),
            [
                'role' => Role::Assistant,
                'content' => '',
                'tool_calls' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'function1',
                            'arguments' => new \stdClass(),
                        ],
                    ],
                ],
            ],
        ];

        yield 'assistant message with multiple tool calls' => [
            new AssistantMessage(toolCalls: [
                new ToolCall('id1', 'function1', ['param1' => 'value1']),
                new ToolCall('id2', 'function2', ['param2' => 'value2']),
            ]),
            [
                'role' => Role::Assistant,
                'content' => '',
                'tool_calls' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'function1',
                            'arguments' => ['param1' => 'value1'],
                        ],
                    ],
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'function2',
                            'arguments' => ['param2' => 'value2'],
                        ],
                    ],
                ],
            ],
        ];

        yield 'assistant message with tool calls and content' => [
            new AssistantMessage(
                content: 'Hello',
                toolCalls: [
                    new ToolCall('id1', 'function1', ['param1' => 'value1']),
                    new ToolCall('id2', 'function2', ['param2' => 'value2']),
                ]
            ),
            [
                'role' => Role::Assistant,
                'content' => 'Hello',
                'tool_calls' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'function1',
                            'arguments' => ['param1' => 'value1'],
                        ],
                    ],
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'function2',
                            'arguments' => ['param2' => 'value2'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
