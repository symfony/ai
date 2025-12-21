<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Contract\Gpt\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\ToolCallNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\ToolNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Serializer;

class MessageBagNormalizerTest extends TestCase
{
    /**
     * @param array{input: array<string, mixed>, model?: string, system?: string} $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(MessageBag $messageBag, array $expected)
    {
        $normalizer = new MessageBagNormalizer();
        $normalizer->setNormalizer(new Serializer([
            new Contract\Normalizer\Message\UserMessageNormalizer(),
            new AssistantMessageNormalizer(),
            new ToolCallMessageNormalizer(),
            new ToolNormalizer(),
            new ToolCallNormalizer(),
            new Contract\Normalizer\Message\SystemMessageNormalizer(),
        ]));

        $actual = $normalizer->normalize($messageBag, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);

        $this->assertEquals($expected, $actual);
    }

    public static function normalizeProvider(): \Generator
    {
        $message = Message::ofUser('Foo');
        $toolCall = new ToolCall('some-id', 'roll-die', ['sides' => 24]);
        $toolCallMessage = Message::ofToolCall($toolCall, 'Critical hit');
        $systemMessage = Message::forSystem('You\'re a nice bot that will not overthrow humanity.');
        $assistantMessage = Message::ofAssistant('Anything else?');
        $toolCallAssistantMessage = Message::ofAssistant(null, [$toolCall]);

        $messageBag = new MessageBag($message, $assistantMessage, $toolCallAssistantMessage, $toolCallMessage);
        $expected = ['input' => [
            [
                'role' => 'user',
                'content' => 'Foo',
            ],
            [
                'role' => 'assistant',
                'type' => 'message',
                'content' => $assistantMessage->getContent(),
            ],
            [
                'arguments' => json_encode($toolCall->getArguments()),
                'call_id' => $toolCall->getId(),
                'name' => $toolCall->getName(),
                'type' => 'function_call',
            ],
            [
                'type' => 'function_call_output',
                'call_id' => $toolCallMessage->getToolCall()->getId(),
                'output' => $toolCallMessage->getContent(),
            ],
        ]];

        yield 'normalize messages' => [$messageBag, $expected];

        yield 'with system message' => [
            $messageBag->with($systemMessage),
            array_merge($expected, ['instructions' => $systemMessage->getContent()]),
        ];
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new MessageBagNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $messageBad = new MessageBag();
        $gpt = new Gpt('o3');

        yield 'supported' => [$messageBad, $gpt, true];
        yield 'unsupported model' => [$messageBad, new Gemini('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
