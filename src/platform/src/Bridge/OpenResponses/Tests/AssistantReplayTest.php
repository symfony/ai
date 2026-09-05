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
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

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
                self::messageItem('Hi there!'),
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
                self::messageItem('It is 10:00 AM.'),
                ['role' => 'user', 'content' => 'And in Paris?'],
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
                self::messageItem('Let me check the clock.'),
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
                self::messageItem('Done.'),
                ['role' => 'user', 'content' => 'Again.'],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function messageItem(string $text): array
    {
        return [
            'role' => 'assistant',
            'type' => 'message',
            'id' => 'msg_1',
            'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]],
        ];
    }
}
