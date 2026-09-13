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
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
        $result = (new ResultConverter())->convert(new RawHttpResult($httpResponse));

        $bag = $bagBuilder($result);
        $payload = AnthropicContract::create()->createRequestPayload(new Claude(Claude::SONNET_4_0), $bag);

        $this->assertEquals($expectedReplayPayload, $payload);
    }

    public function testRejectsWebSearchCallerReferencingUnsupportedCodeExecution()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['content' => [[
            'type' => 'server_tool_use',
            'id' => 'srvtoolu_web_1',
            'name' => 'web_search',
            'input' => ['query' => 'Symfony AI'],
            'caller' => ['type' => 'code_execution_20260120', 'tool_id' => 'srvtoolu_code_1'],
        ]]]));
        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $result = (new ResultConverter())->convert(new RawHttpResult($httpResponse));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('surrounding code execution blocks are not supported');

        AnthropicContract::create()->createRequestPayload(new Claude(Claude::SONNET_4_0), new MessageBag(
            Message::ofAssistant($result),
        ));
    }

    public function testStreamedWebSearchRoundTripPreservesAssistantBlockOrder()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $result = (new ResultConverter())->convert(new InMemoryRawResult(dataStream: [
            ['type' => 'message_start', 'message' => ['id' => 'msg_1', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => 'I should search the web.']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'sig_web']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'content_block_start', 'index' => 1, 'content_block' => [
                'type' => 'server_tool_use',
                'id' => 'srvtoolu_web_1',
                'name' => 'web_search',
                'input' => [],
                'caller' => ['type' => 'direct'],
            ]],
            ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"query":"Symfony AI"}']],
            ['type' => 'content_block_stop', 'index' => 1],
            ['type' => 'content_block_start', 'index' => 2, 'content_block' => [
                'type' => 'web_search_tool_result',
                'tool_use_id' => 'srvtoolu_web_1',
                'content' => [],
                'caller' => ['type' => 'direct'],
            ]],
            ['type' => 'content_block_stop', 'index' => 2],
            ['type' => 'content_block_start', 'index' => 3, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 3, 'delta' => ['type' => 'text_delta', 'text' => 'Symfony AI integrates AI capabilities.']],
            ['type' => 'content_block_stop', 'index' => 3],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn']],
            ['type' => 'message_stop'],
        ], object: $response), ['stream' => true]);

        $payload = AnthropicContract::create()->createRequestPayload(new Claude(Claude::SONNET_4_0), new MessageBag(
            Message::ofUser('What is Symfony AI?'),
            Message::ofAssistant($result),
            Message::ofUser('Tell me more.'),
        ));

        $this->assertEquals([
            'messages' => [
                ['role' => 'user', 'content' => 'What is Symfony AI?'],
                [
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'thinking', 'thinking' => 'I should search the web.', 'signature' => 'sig_web'],
                        [
                            'type' => 'server_tool_use',
                            'name' => 'web_search',
                            'input' => ['query' => 'Symfony AI'],
                            'id' => 'srvtoolu_web_1',
                            'caller' => ['type' => 'direct'],
                        ],
                        [
                            'type' => 'web_search_tool_result',
                            'tool_use_id' => 'srvtoolu_web_1',
                            'content' => [],
                            'caller' => ['type' => 'direct'],
                        ],
                        ['type' => 'text', 'text' => 'Symfony AI integrates AI capabilities.'],
                    ],
                ],
                ['role' => 'user', 'content' => 'Tell me more.'],
            ],
            'model' => 'claude-sonnet-4-0',
        ], $payload);
    }

    public function testStreamedPausedWebSearchRoundTripPreservesServerToolBlock()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $result = (new ResultConverter())->convert(new InMemoryRawResult(dataStream: [
            ['type' => 'message_start', 'message' => ['id' => 'msg_1', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => [
                'type' => 'server_tool_use',
                'id' => 'srvtoolu_web_1',
                'name' => 'web_search',
                'input' => [],
            ]],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"query":"Symfony AI"}']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'pause_turn']],
            ['type' => 'message_stop'],
        ], object: $response), ['stream' => true]);

        $payload = AnthropicContract::create()->createRequestPayload(new Claude(Claude::SONNET_4_0), new MessageBag(
            Message::ofUser('What is Symfony AI?'),
            Message::ofAssistant($result),
        ));

        $this->assertEquals([
            'messages' => [
                ['role' => 'user', 'content' => 'What is Symfony AI?'],
                ['role' => 'assistant', 'content' => [[
                    'type' => 'server_tool_use',
                    'id' => 'srvtoolu_web_1',
                    'name' => 'web_search',
                    'input' => ['query' => 'Symfony AI'],
                ]]],
            ],
            'model' => 'claude-sonnet-4-0',
        ], $payload);
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

        yield 'thinking, web search and text replay in provider order' => [
            [
                'content' => [
                    [
                        'type' => 'thinking',
                        'thinking' => 'I should search the web.',
                        'signature' => 'sig_web',
                    ],
                    [
                        'type' => 'server_tool_use',
                        'id' => 'srvtoolu_web_1',
                        'name' => 'web_search',
                        'input' => ['query' => 'Symfony AI'],
                        'caller' => ['type' => 'direct'],
                    ],
                    [
                        'type' => 'web_search_tool_result',
                        'tool_use_id' => 'srvtoolu_web_1',
                        'content' => [[
                            'type' => 'web_search_result',
                            'url' => 'https://symfony.com/ai',
                            'title' => 'Symfony AI',
                            'snippet' => 'Symfony AI integrates AI capabilities.',
                            'favicon' => 'https://symfony.com/favicon.ico',
                            'encrypted_content' => 'encrypted-result',
                            'page_age' => 'August 30, 2026',
                        ]],
                        'caller' => ['type' => 'direct'],
                    ],
                    ['type' => 'text', 'text' => 'Symfony AI integrates AI capabilities.'],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What is Symfony AI?'),
                Message::ofAssistant($result),
                Message::ofUser('Tell me more.'),
            ),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'What is Symfony AI?'],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'thinking',
                                'thinking' => 'I should search the web.',
                                'signature' => 'sig_web',
                            ],
                            [
                                'type' => 'server_tool_use',
                                'name' => 'web_search',
                                'input' => ['query' => 'Symfony AI'],
                                'id' => 'srvtoolu_web_1',
                                'caller' => ['type' => 'direct'],
                            ],
                            [
                                'type' => 'web_search_tool_result',
                                'tool_use_id' => 'srvtoolu_web_1',
                                'content' => [[
                                    'type' => 'web_search_result',
                                    'url' => 'https://symfony.com/ai',
                                    'title' => 'Symfony AI',
                                    'snippet' => 'Symfony AI integrates AI capabilities.',
                                    'favicon' => 'https://symfony.com/favicon.ico',
                                    'encrypted_content' => 'encrypted-result',
                                    'page_age' => 'August 30, 2026',
                                ]],
                                'caller' => ['type' => 'direct'],
                            ],
                            ['type' => 'text', 'text' => 'Symfony AI integrates AI capabilities.'],
                        ],
                    ],
                    ['role' => 'user', 'content' => 'Tell me more.'],
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
                'system' => [['type' => 'text', 'text' => 'You are a pirate.']],
                'model' => 'claude-sonnet-4-0',
            ],
        ];
    }
}
