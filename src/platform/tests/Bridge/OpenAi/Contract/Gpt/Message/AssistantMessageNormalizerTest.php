<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Contract\Gpt\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\ToolCallNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Serializer;

class AssistantMessageNormalizerTest extends TestCase
{
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
                'content' => 'Foo',
            ],
        ];

        $toolCall = new ToolCall('some-id', 'roll-die', ['sides' => 24]);
        yield 'with tool calls' => [
            Message::ofAssistant(null, [$toolCall]),
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
        yield 'unsupported model' => [$assistantMessage, new Gemini('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
