<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AnthropicContract;
use Symfony\AI\Platform\Bridge\Anthropic\MessagesClient;
use Symfony\AI\Platform\Bridge\Anthropic\Transport\HttpTransport;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * End-to-end replay test: feed a fixture provider response into ResultConverter,
 * build an assistant message via Message::ofAssistant($result), append the next
 * user/tool turn, and assert the byte-shape of the request that would be sent
 * back to the provider on turn 2.
 *
 * Catches regressions in the round-trip path that AssistantMessageNormalizer
 * tests in isolation cannot: ordering preservation across the bag, signature
 * survival, tool-call id pairing, empty-content handling.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantReplayTest extends TestCase
{
    /**
     * @param array<string, mixed> $providerResponse
     * @param array<string, mixed> $expectedReplayPayload
     */
    #[DataProvider('provideReplayScenarios')]
    public function testRoundTrip(array $providerResponse, callable $bagBuilder, array $expectedReplayPayload)
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($providerResponse));
        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $result = (new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused')))->convert(new RawHttpResult($httpResponse));

        $bag = $bagBuilder($result);
        $payload = AnthropicContract::create()->createRequestPayload(new Claude(Claude::SONNET_4_0), $bag);

        $this->assertEquals($expectedReplayPayload, $payload);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: callable, 2: array<string, mixed>}>
     */
    public static function provideReplayScenarios(): iterable
    {
        yield 'text-only assistant turn replays as plain string content' => [
            [
                'content' => [
                    ['type' => 'text', 'text' => 'Hi there!'],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Hello'),
                Message::ofAssistant($result),
                Message::ofUser('Tell me more.'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                    ['role' => 'assistant', 'content' => 'Hi there!'],
                    ['role' => 'user', 'content' => 'Tell me more.'],
                ],
                'model' => 'claude-sonnet-4-0',
            ],
        ];

        yield 'text + tool_use assistant turn preserves order and ids' => [
            [
                'content' => [
                    ['type' => 'text', 'text' => "I'll look that up."],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_abc',
                        'name' => 'wikipedia',
                        'input' => ['query' => 'Symfony'],
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What is Symfony?'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('toolu_abc', 'wikipedia', ['query' => 'Symfony']), 'Symfony is a PHP framework.'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is Symfony?'],
                    [
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'text', 'text' => "I'll look that up."],
                            [
                                'type' => 'tool_use',
                                'id' => 'toolu_abc',
                                'name' => 'wikipedia',
                                'input' => ['query' => 'Symfony'],
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'tool_result',
                                'tool_use_id' => 'toolu_abc',
                                'content' => 'Symfony is a PHP framework.',
                            ],
                        ],
                    ],
                ],
                'model' => 'claude-sonnet-4-0',
            ],
        ];

        yield 'thinking + text + tool_use assistant turn preserves signature and ordering' => [
            [
                'content' => [
                    [
                        'type' => 'thinking',
                        'thinking' => 'Let me think about which tool to use.',
                        'signature' => 'sig_xyz',
                    ],
                    ['type' => 'text', 'text' => "I'll search Wikipedia."],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_def',
                        'name' => 'wikipedia',
                        'input' => ['query' => 'Symfony'],
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What is Symfony?'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('toolu_def', 'wikipedia', ['query' => 'Symfony']), 'Symfony is a PHP framework.'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is Symfony?'],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'thinking',
                                'thinking' => 'Let me think about which tool to use.',
                                'signature' => 'sig_xyz',
                            ],
                            ['type' => 'text', 'text' => "I'll search Wikipedia."],
                            [
                                'type' => 'tool_use',
                                'id' => 'toolu_def',
                                'name' => 'wikipedia',
                                'input' => ['query' => 'Symfony'],
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'tool_result',
                                'tool_use_id' => 'toolu_def',
                                'content' => 'Symfony is a PHP framework.',
                            ],
                        ],
                    ],
                ],
                'model' => 'claude-sonnet-4-0',
            ],
        ];

        yield 'thinking-only response replays as a thinking block' => [
            [
                'content' => [
                    [
                        'type' => 'thinking',
                        'thinking' => 'Reasoning only...',
                        'signature' => 'sig_t1',
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Think about this.'),
                Message::ofAssistant($result),
                Message::ofUser('Now answer.'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'Think about this.'],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'thinking',
                                'thinking' => 'Reasoning only...',
                                'signature' => 'sig_t1',
                            ],
                        ],
                    ],
                    ['role' => 'user', 'content' => 'Now answer.'],
                ],
                'model' => 'claude-sonnet-4-0',
            ],
        ];

        yield 'tool_use without preceding text replays as a single block' => [
            [
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_123',
                        'name' => 'clock',
                        'input' => [],
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What time is it?'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('toolu_123', 'clock', []), '2026-05-09T12:00:00Z'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'What time is it?'],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'tool_use',
                                'id' => 'toolu_123',
                                'name' => 'clock',
                                'input' => new \stdClass(),
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'tool_result',
                                'tool_use_id' => 'toolu_123',
                                'content' => '2026-05-09T12:00:00Z',
                            ],
                        ],
                    ],
                ],
                'model' => 'claude-sonnet-4-0',
            ],
        ];

        yield 'system message lifts to system field' => [
            [
                'content' => [
                    ['type' => 'text', 'text' => 'Aye!'],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::forSystem('You are a pirate.'),
                Message::ofUser('Greet me.'),
                Message::ofAssistant($result),
                Message::ofUser('Again!'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'Greet me.'],
                    ['role' => 'assistant', 'content' => 'Aye!'],
                    ['role' => 'user', 'content' => 'Again!'],
                ],
                'system' => 'You are a pirate.',
                'model' => 'claude-sonnet-4-0',
            ],
        ];
    }
}
