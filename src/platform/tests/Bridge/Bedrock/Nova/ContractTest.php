<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Bedrock\Nova;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;

final class ContractTest extends TestCase
{
    #[DataProvider('provideMessageBag')]
    public function testConvert(MessageBag $bag, array $expected)
    {
        $contract = Contract::create(
            new AssistantMessageNormalizer(),
            new MessageBagNormalizer(),
            new ToolCallMessageNormalizer(),
            new ToolNormalizer(),
            new UserMessageNormalizer(),
        );

        $this->assertEquals($expected, $contract->createRequestPayload(new Nova('nova-pro'), $bag));
    }

    /**
     * @return iterable<array{0: MessageBag, 1: array}>
     */
    public static function provideMessageBag(): iterable
    {
        yield 'simple text' => [
            new MessageBag(Message::ofUser('Write a story about a magic backpack.')),
            [
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'Write a story about a magic backpack.']]],
                ],
            ],
        ];

        yield 'with assistant message' => [
            new MessageBag(
                Message::ofUser('Hello'),
                Message::ofAssistant('Great to meet you. What would you like to know?'),
                Message::ofUser('I have two dogs in my house. How many paws are in my house?'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'Hello']]],
                    ['role' => 'assistant', 'content' => [['text' => 'Great to meet you. What would you like to know?']]],
                    ['role' => 'user', 'content' => [['text' => 'I have two dogs in my house. How many paws are in my house?']]],
                ],
            ],
        ];

        yield 'with system messages' => [
            new MessageBag(
                Message::forSystem('You are a cat. Your name is Neko.'),
                Message::ofUser('Hello there'),
            ),
            [
                'system' => [['text' => 'You are a cat. Your name is Neko.']],
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'Hello there']]],
                ],
            ],
        ];

        yield 'with tool use' => [
            new MessageBag(
                Message::ofUser('Hello there, what is the time?'),
                Message::ofAssistant(toolCalls: [new ToolCall('123456', 'clock', [])]),
                Message::ofToolCall(new ToolCall('123456', 'clock', []), '2023-10-01T10:00:00+00:00'),
                Message::ofAssistant('It is 10:00 AM.'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => 'Hello there, what is the time?']]],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'toolUse' => [
                                    'toolUseId' => '123456',
                                    'name' => 'clock',
                                    'input' => new \stdClass(),
                                ],
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'toolResult' => [
                                    'toolUseId' => '123456',
                                    'content' => [
                                        ['json' => '2023-10-01T10:00:00+00:00'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    ['role' => 'assistant', 'content' => [['text' => 'It is 10:00 AM.']]],
                ],
            ],
        ];
    }
}
