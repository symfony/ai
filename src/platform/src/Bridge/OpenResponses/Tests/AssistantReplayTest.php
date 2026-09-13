<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\OpenResponsesContract;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Asserts the shape of the request sent back on turn 2, which normalizing each message in
 * isolation cannot prove.
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
        $httpResponse = $httpClient->request('POST', 'https://api.openai.com/v1/responses');
        $result = (new ResultConverter())->convert(new RawHttpResult($httpResponse));

        $bag = $bagBuilder($result);
        $payload = OpenResponsesContract::create()->createRequestPayload(new ResponsesModel('gpt-5'), $bag);

        $this->assertEquals($expectedReplayPayload, $payload);
    }

    public function testStreamedWebSearchRoundTripPreservesAssistantItemOrder()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $result = (new ResultConverter())->convert(new InMemoryRawResult(dataStream: [
            [
                'type' => 'response.output_item.done',
                'item' => [
                    'type' => 'web_search_call',
                    'id' => 'ws_1',
                    'status' => 'completed',
                    'action' => ['type' => 'open_page', 'url' => 'https://symfony.com/ai'],
                ],
            ],
            ['type' => 'response.output_text.delta', 'delta' => 'Symfony AI integrates AI capabilities.'],
            ['type' => 'response.completed', 'response' => ['output' => []]],
        ], object: $response), ['stream' => true]);

        $payload = OpenResponsesContract::create()->createRequestPayload(new ResponsesModel('gpt-5'), new MessageBag(
            Message::ofUser('What is Symfony AI?'),
            Message::ofAssistant($result),
            Message::ofUser('Tell me more.'),
        ));

        $this->assertEquals(['input' => [
            ['role' => 'user', 'content' => 'What is Symfony AI?'],
            [
                'type' => 'web_search_call',
                'action' => ['type' => 'open_page', 'url' => 'https://symfony.com/ai'],
                'id' => 'ws_1',
                'status' => 'completed',
            ],
            ['role' => 'assistant', 'type' => 'message', 'content' => 'Symfony AI integrates AI capabilities.'],
            ['role' => 'user', 'content' => 'Tell me more.'],
        ]], $payload);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: callable, 2: array<string, mixed>}>
     */
    public static function provideReplayScenarios(): iterable
    {
        $reasoningItem = [
            'type' => 'reasoning',
            'id' => 'rs_1',
            'summary' => [['type' => 'summary_text', 'text' => 'The user wants the time.']],
            'encrypted_content' => 'gAAAAA-encrypted',
        ];

        yield 'text-only assistant turn' => [
            [
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => 'Hi there!']],
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Hello'),
                Message::ofAssistant($result),
                Message::ofUser('Tell me more.'),
            ),
            ['input' => [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'type' => 'message', 'content' => 'Hi there!'],
                ['role' => 'user', 'content' => 'Tell me more.'],
            ]],
        ];

        yield 'reasoning item survives the round trip with its encrypted content' => [
            [
                'output' => [
                    $reasoningItem,
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => 'It is 10:00 AM.']],
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What time is it?'),
                Message::ofAssistant($result),
                Message::ofUser('And in Paris?'),
            ),
            ['input' => [
                ['role' => 'user', 'content' => 'What time is it?'],
                $reasoningItem,
                ['role' => 'assistant', 'type' => 'message', 'content' => 'It is 10:00 AM.'],
                ['role' => 'user', 'content' => 'And in Paris?'],
            ]],
        ];

        yield 'reasoning, web search and text replay in provider order' => [
            [
                'output' => [
                    $reasoningItem,
                    [
                        'type' => 'web_search_call',
                        'id' => 'ws_1',
                        'status' => 'completed',
                        'action' => ['type' => 'open_page', 'url' => 'https://symfony.com/ai'],
                    ],
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => 'Symfony AI integrates AI capabilities.']],
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What is Symfony AI?'),
                Message::ofAssistant($result),
                Message::ofUser('Tell me more.'),
            ),
            ['input' => [
                ['role' => 'user', 'content' => 'What is Symfony AI?'],
                $reasoningItem,
                [
                    'type' => 'web_search_call',
                    'action' => ['type' => 'open_page', 'url' => 'https://symfony.com/ai'],
                    'id' => 'ws_1',
                    'status' => 'completed',
                ],
                ['role' => 'assistant', 'type' => 'message', 'content' => 'Symfony AI integrates AI capabilities.'],
                ['role' => 'user', 'content' => 'Tell me more.'],
            ]],
        ];

        yield 'reasoning, text and a tool call replay in provider order' => [
            [
                'output' => [
                    $reasoningItem,
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => 'Let me check the clock.']],
                    ],
                    [
                        'type' => 'function_call',
                        'id' => 'fc_1',
                        'call_id' => 'call_1',
                        'name' => 'clock',
                        'arguments' => '{}',
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What time is it?'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('call_1', 'clock', []), '10:00 AM'),
            ),
            ['input' => [
                ['role' => 'user', 'content' => 'What time is it?'],
                $reasoningItem,
                ['role' => 'assistant', 'type' => 'message', 'content' => 'Let me check the clock.'],
                [
                    'type' => 'function_call',
                    'call_id' => 'call_1',
                    'name' => 'clock',
                    'arguments' => '{}',
                ],
                [
                    'type' => 'function_call_output',
                    'call_id' => 'call_1',
                    'output' => '10:00 AM',
                ],
            ]],
        ];

        yield 'encrypted reasoning without a summary is still replayed' => [
            [
                'output' => [
                    [
                        'type' => 'reasoning',
                        'id' => 'rs_2',
                        'summary' => [],
                        'encrypted_content' => 'gAAAAA-opaque',
                    ],
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => 'Done.']],
                    ],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Think hard.'),
                Message::ofAssistant($result),
                Message::ofUser('Again.'),
            ),
            ['input' => [
                ['role' => 'user', 'content' => 'Think hard.'],
                [
                    'type' => 'reasoning',
                    'id' => 'rs_2',
                    'summary' => [],
                    'encrypted_content' => 'gAAAAA-opaque',
                ],
                ['role' => 'assistant', 'type' => 'message', 'content' => 'Done.'],
                ['role' => 'user', 'content' => 'Again.'],
            ]],
        ];
    }
}
