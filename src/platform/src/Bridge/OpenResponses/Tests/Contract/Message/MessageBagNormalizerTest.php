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
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\ToolCallNormalizer;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\ToolNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
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
        $actual = self::createNormalizer()->normalize($messageBag, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);

        $this->assertEquals($expected, $actual);
    }

    public function testNormalizeThrowsWhenInputIsEmptyAndNoAlternativeInputProvided()
    {
        $normalizer = self::createNormalizer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The message bag must contain at least one non-system message, or one of the "previous_response_id", "prompt" or "conversation_id" options must be provided.');

        $normalizer->normalize(
            new MessageBag(Message::forSystem('You are a helpful assistant.')),
            null,
            [Contract::CONTEXT_MODEL => new Gpt('o3')],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('alternativeInputProvider')]
    public function testNormalizeAllowsEmptyInputWithAlternativeInput(array $options)
    {
        $normalizer = self::createNormalizer();

        $actual = $normalizer->normalize(
            new MessageBag(Message::forSystem('You are a helpful assistant.')),
            null,
            [Contract::CONTEXT_MODEL => new Gpt('o3'), Contract::CONTEXT_OPTIONS => $options],
        );

        $this->assertSame([], $actual['input']);
        $this->assertSame('You are a helpful assistant.', $actual['instructions']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function alternativeInputProvider(): iterable
    {
        yield 'previous_response_id' => [['previous_response_id' => 'resp_123']];
        yield 'prompt' => [['prompt' => ['id' => 'pmpt_123']]];
        yield 'conversation_id' => [['conversation_id' => 'conv_123']];
    }

    public static function normalizeProvider(): \Generator
    {
        $message = Message::ofUser('Foo');
        $toolCall = new ToolCall('some-id', 'roll-die', ['sides' => 24]);
        $toolCallMessage = Message::ofToolCall($toolCall, 'Critical hit');
        $systemMessage = Message::forSystem('You\'re a nice bot that will not overthrow humanity.');
        $assistantMessage = Message::ofAssistant('Anything else?');
        $toolCallAssistantMessage = Message::ofAssistant($toolCall);

        $messageBag = new MessageBag($message, $assistantMessage, $toolCallAssistantMessage, $toolCallMessage);
        $expected = ['input' => [
            [
                'role' => 'user',
                'content' => 'Foo',
            ],
            [
                'role' => 'assistant',
                'type' => 'message',
                'content' => 'Anything else?',
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
                'output' => $toolCallMessage->asText(),
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
        yield 'unsupported model' => [$messageBad, new Model('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }

    private static function createNormalizer(): MessageBagNormalizer
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

        return $normalizer;
    }
}
