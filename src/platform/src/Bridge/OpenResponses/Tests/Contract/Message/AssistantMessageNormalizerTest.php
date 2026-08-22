<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests\Contract\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\ToolCallNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Serializer;

class AssistantMessageNormalizerTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(AssistantMessage $message, array $expected)
    {
        $normalizer = new AssistantMessageNormalizer();
        $normalizer->setNormalizer(new Serializer([new ToolCallNormalizer()]));

        $actual = $normalizer->normalize($message, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);
        $this->assertEquals($expected, $actual);
    }

    public static function normalizeProvider(): \Generator
    {
        $message = Message::ofAssistant('Foo');
        yield 'without tool calls' => [
            $message,
            [[
                'role' => 'assistant',
                'type' => 'message',
                'content' => 'Foo',
            ]],
        ];

        $toolCall = new ToolCall('some-id', 'roll-die', ['sides' => 24]);
        yield 'with tool calls' => [
            Message::ofAssistant($toolCall),
            [
                [
                    'arguments' => json_encode($toolCall->getArguments()),
                    'call_id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'type' => 'function_call',
                ],
            ],
        ];

        yield 'text is preserved around tool calls' => [
            Message::ofAssistant(new Text('Before'), $toolCall, new Text('After')),
            [
                ['role' => 'assistant', 'type' => 'message', 'content' => 'Before'],
                [
                    'arguments' => json_encode($toolCall->getArguments()),
                    'call_id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'type' => 'function_call',
                ],
                ['role' => 'assistant', 'type' => 'message', 'content' => 'After'],
            ],
        ];

        $reasoningItem = [
            'type' => 'reasoning',
            'id' => 'rs_1',
            'summary' => [['type' => 'summary_text', 'text' => 'Pondering.']],
            'encrypted_content' => 'gAAAAA-encrypted',
        ];
        yield 'reasoning items are replayed before tool calls' => [
            Message::ofAssistant(new Thinking('Pondering.', json_encode($reasoningItem)), $toolCall),
            [
                $reasoningItem,
                [
                    'arguments' => json_encode($toolCall->getArguments()),
                    'call_id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'type' => 'function_call',
                ],
            ],
        ];

        $secondReasoningItem = [
            'type' => 'reasoning',
            'id' => 'rs_2',
            'summary' => [['type' => 'summary_text', 'text' => 'More pondering.']],
            'encrypted_content' => 'gAAAAA-more-encrypted',
        ];
        yield 'reasoning items keep their order around tool calls' => [
            Message::ofAssistant(
                new Thinking('Pondering.', json_encode($reasoningItem)),
                $toolCall,
                new Thinking('More pondering.', json_encode($secondReasoningItem)),
            ),
            [
                $reasoningItem,
                [
                    'arguments' => json_encode($toolCall->getArguments()),
                    'call_id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'type' => 'function_call',
                ],
                $secondReasoningItem,
            ],
        ];

        yield 'reasoning items are replayed before the message' => [
            Message::ofAssistant(new Thinking('Pondering.', json_encode($reasoningItem)), new Text('Foo')),
            [
                $reasoningItem,
                [
                    'role' => 'assistant',
                    'type' => 'message',
                    'content' => 'Foo',
                ],
            ],
        ];

        yield 'thinking without signature is not replayed' => [
            Message::ofAssistant(new Thinking('Pondering.'), new Text('Foo')),
            [[
                'role' => 'assistant',
                'type' => 'message',
                'content' => 'Foo',
            ]],
        ];

        yield 'non-reasoning signature is ignored' => [
            Message::ofAssistant(new Thinking('Pondering.', 'anthropic-opaque-signature'), new Text('Foo')),
            [[
                'role' => 'assistant',
                'type' => 'message',
                'content' => 'Foo',
            ]],
        ];
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new AssistantMessageNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $assistantMessage = Message::ofAssistant('Foo');
        $gpt = new Gpt('o3');

        yield 'supported' => [$assistantMessage, $gpt, true];
        yield 'unsupported model' => [$assistantMessage, new Model('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
