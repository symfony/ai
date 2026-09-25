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
use Symfony\Component\Uid\UuidV7;

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

    public function testMessageItemIdIsDerivedFromTheMessageId()
    {
        $message = Message::ofAssistant('Foo')->withId(UuidV7::fromString('0198e1d6-1234-7abc-8def-0123456789ab'));

        $normalizer = new AssistantMessageNormalizer();
        $normalizer->setNormalizer(new Serializer([new ToolCallNormalizer()]));

        $actual = $normalizer->normalize($message, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);

        $this->assertSame('msg_0198e1d612347abc8def0123456789ab', $actual[0]['id']);
    }

    public static function normalizeProvider(): \Generator
    {
        $message = Message::ofAssistant('Foo');
        yield 'without tool calls' => [
            $message,
            [self::messageItem($message, 'Foo')],
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

        $reasoningThenText = Message::ofAssistant(new Thinking('Pondering.', json_encode($reasoningItem)), new Text('Foo'));
        yield 'reasoning items are replayed before the message' => [
            $reasoningThenText,
            [
                $reasoningItem,
                self::messageItem($reasoningThenText, 'Foo'),
            ],
        ];

        $unsignedThinking = Message::ofAssistant(new Thinking('Pondering.'), new Text('Foo'));
        yield 'thinking without signature is not replayed' => [
            $unsignedThinking,
            [self::messageItem($unsignedThinking, 'Foo')],
        ];

        $opaqueSignature = Message::ofAssistant(new Thinking('Pondering.', 'anthropic-opaque-signature'), new Text('Foo'));
        yield 'non-reasoning signature is ignored' => [
            $opaqueSignature,
            [self::messageItem($opaqueSignature, 'Foo')],
        ];

        $normalizedToolCall = [
            'arguments' => json_encode($toolCall->getArguments()),
            'call_id' => $toolCall->getId(),
            'name' => $toolCall->getName(),
            'type' => 'function_call',
        ];

        $textThenToolCall = Message::ofAssistant(new Text('Let me roll.'), $toolCall);
        yield 'text accompanying a tool call is replayed, not dropped' => [
            $textThenToolCall,
            [
                self::messageItem($textThenToolCall, 'Let me roll.'),
                $normalizedToolCall,
            ],
        ];

        $textAroundToolCall = Message::ofAssistant(new Text('Before. '), $toolCall, new Text('After.'));
        yield 'text on both sides of a tool call keeps its positions' => [
            $textAroundToolCall,
            [
                self::messageItem($textAroundToolCall, 'Before. '),
                $normalizedToolCall,
                self::messageItem($textAroundToolCall, 'After.', 1),
            ],
        ];

        $reasoningBetweenText = Message::ofAssistant(new Text('Thinking about it. '), new Thinking('Pondering.', json_encode($reasoningItem)), new Text('Done.'));
        yield 'a reasoning item after text stays after it' => [
            $reasoningBetweenText,
            [
                self::messageItem($reasoningBetweenText, 'Thinking about it. '),
                $reasoningItem,
                self::messageItem($reasoningBetweenText, 'Done.', 1),
            ],
        ];

        $reasoningTextToolCall = Message::ofAssistant(new Thinking('Pondering.', json_encode($reasoningItem)), new Text('Let me roll.'), $toolCall);
        yield 'reasoning, text and a tool call in the order the model produced them' => [
            $reasoningTextToolCall,
            [
                $reasoningItem,
                self::messageItem($reasoningTextToolCall, 'Let me roll.'),
                $normalizedToolCall,
            ],
        ];

        $providerSignature = json_encode(['type' => 'message', 'id' => 'msg_from_provider']);
        yield 'the provider message id is replayed instead of a generated one' => [
            Message::ofAssistant(new Text('Foo', $providerSignature)),
            [[
                'role' => 'assistant',
                'type' => 'message',
                'id' => 'msg_from_provider',
                'status' => 'completed',
                'content' => [['type' => 'output_text', 'text' => 'Foo', 'annotations' => []]],
            ]],
        ];

        $opaqueTextSignature = Message::ofAssistant(new Text('Foo', 'gemini-opaque-signature'));
        yield 'an opaque text signature from another provider is ignored' => [
            $opaqueTextSignature,
            [self::messageItem($opaqueTextSignature, 'Foo')],
        ];

        $nothingReplayable = Message::ofAssistant(new Thinking('Pondering.'));
        yield 'an assistant turn with nothing replayable keeps the empty message' => [
            $nothingReplayable,
            [self::messageItem($nothingReplayable, null)],
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

    /**
     * @return array<string, mixed>
     */
    private static function messageItem(AssistantMessage $message, ?string $text, int $index = 0): array
    {
        $id = 'msg_'.str_replace('-', '', $message->getId()->toRfc4122());

        return [
            'role' => 'assistant',
            'type' => 'message',
            'id' => 0 === $index ? $id : $id.\sprintf('%02d', $index),
            'status' => 'completed',
            'content' => null === $text ? [] : [
                ['type' => 'output_text', 'text' => $text, 'annotations' => []],
            ],
        ];
    }
}
