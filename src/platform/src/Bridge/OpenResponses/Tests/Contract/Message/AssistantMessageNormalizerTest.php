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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Serializer;

class AssistantMessageNormalizerTest extends TestCase
{
    /**
     * @param array{role: 'assistant', type: 'message', id: string, status: 'completed', content: list<array{type: 'output_text', text: string, annotations: array{}}>} $expected
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
            [
                'role' => 'assistant',
                'type' => 'message',
                'id' => 'msg_'.str_replace('-', '', $message->getId()->toRfc4122()),
                'status' => 'completed',
                'content' => [
                    ['type' => 'output_text', 'text' => 'Foo', 'annotations' => []],
                ],
            ],
        ];

        $multiPartMessage = Message::ofAssistant(new Text('Foo'), new Text('Bar'));
        yield 'with multiple text parts' => [
            $multiPartMessage,
            [
                'role' => 'assistant',
                'type' => 'message',
                'id' => 'msg_'.str_replace('-', '', $multiPartMessage->getId()->toRfc4122()),
                'status' => 'completed',
                'content' => [
                    ['type' => 'output_text', 'text' => 'Foo', 'annotations' => []],
                    ['type' => 'output_text', 'text' => 'Bar', 'annotations' => []],
                ],
            ],
        ];

        $emptyMessage = Message::ofAssistant();
        yield 'without content' => [
            $emptyMessage,
            [
                'role' => 'assistant',
                'type' => 'message',
                'id' => 'msg_'.str_replace('-', '', $emptyMessage->getId()->toRfc4122()),
                'status' => 'completed',
                'content' => [],
            ],
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
