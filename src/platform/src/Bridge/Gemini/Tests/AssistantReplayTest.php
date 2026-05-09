<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
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
 * back to Gemini on turn 2.
 *
 * Particularly important for Gemini because of `thoughtSignature` round-tripping
 * — every assistant part (text, thought, functionCall) may carry a signature
 * that has to survive the conversion → message → normalize loop intact.
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
        $httpResponse = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
        $result = (new ResultConverter())->convert(new RawHttpResult($httpResponse));

        $bag = $bagBuilder($result);
        $payload = GeminiContract::create()->createRequestPayload(new Gemini('gemini-2.5-flash'), $bag);

        $this->assertEquals($expectedReplayPayload, $payload);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: callable, 2: array<string, mixed>}>
     */
    public static function provideReplayScenarios(): iterable
    {
        yield 'plain text turn replays as a single text part' => [
            [
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Paris.']]]],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What is the capital of France?'),
                Message::ofAssistant($result),
                Message::ofUser('And of Germany?'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'What is the capital of France?']]],
                    ['role' => 'model', 'parts' => [['text' => 'Paris.']]],
                    ['role' => 'user', 'parts' => [['text' => 'And of Germany?']]],
                ],
            ],
        ];

        yield 'text + functionCall preserves order and ids' => [
            [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => "I'll check the time."],
                        ['functionCall' => ['id' => 'call_1', 'name' => 'clock', 'args' => []]],
                    ]]],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('What time is it?'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('call_1', 'clock', []), '12:00'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'What time is it?']]],
                    ['role' => 'model', 'parts' => [
                        ['text' => "I'll check the time."],
                        ['functionCall' => ['id' => 'call_1', 'name' => 'clock']],
                    ]],
                    ['role' => 'user', 'parts' => [
                        ['functionResponse' => [
                            'id' => 'call_1',
                            'name' => 'clock',
                            'response' => ['rawResponse' => '12:00'],
                        ]],
                    ]],
                ],
            ],
        ];

        yield 'thought + text round-trips signature on each part' => [
            [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'Reasoning step.', 'thought' => true, 'thoughtSignature' => 'sig_thought'],
                        ['text' => 'Final answer.', 'thoughtSignature' => 'sig_text'],
                    ]]],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Solve it.'),
                Message::ofAssistant($result),
                Message::ofUser('Now another.'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Solve it.']]],
                    ['role' => 'model', 'parts' => [
                        ['text' => 'Reasoning step.', 'thought' => true, 'thoughtSignature' => 'sig_thought'],
                        ['text' => 'Final answer.', 'thoughtSignature' => 'sig_text'],
                    ]],
                    ['role' => 'user', 'parts' => [['text' => 'Now another.']]],
                ],
            ],
        ];

        yield 'signed functionCall round-trips signature' => [
            [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['functionCall' => ['id' => 'call_2', 'name' => 'search', 'args' => ['q' => 'symfony']], 'thoughtSignature' => 'sig_call'],
                    ]]],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Search for symfony.'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('call_2', 'search', ['q' => 'symfony']), 'PHP framework.'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Search for symfony.']]],
                    ['role' => 'model', 'parts' => [
                        ['functionCall' => ['id' => 'call_2', 'name' => 'search', 'args' => ['q' => 'symfony']], 'thoughtSignature' => 'sig_call'],
                    ]],
                    ['role' => 'user', 'parts' => [
                        ['functionResponse' => [
                            'id' => 'call_2',
                            'name' => 'search',
                            'response' => ['rawResponse' => 'PHP framework.'],
                        ]],
                    ]],
                ],
            ],
        ];

        yield 'thought + text + functionCall with mixed signatures preserves order' => [
            [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'First thought.', 'thought' => true, 'thoughtSignature' => 'sig_t1'],
                        ['text' => 'Plain visible text.'],
                        ['functionCall' => ['id' => 'call_3', 'name' => 'lookup', 'args' => ['q' => 'x']], 'thoughtSignature' => 'sig_call3'],
                    ]]],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::ofUser('Do work.'),
                Message::ofAssistant($result),
                Message::ofToolCall(new ToolCall('call_3', 'lookup', ['q' => 'x']), 'ok'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Do work.']]],
                    ['role' => 'model', 'parts' => [
                        ['text' => 'First thought.', 'thought' => true, 'thoughtSignature' => 'sig_t1'],
                        ['text' => 'Plain visible text.'],
                        ['functionCall' => ['id' => 'call_3', 'name' => 'lookup', 'args' => ['q' => 'x']], 'thoughtSignature' => 'sig_call3'],
                    ]],
                    ['role' => 'user', 'parts' => [
                        ['functionResponse' => [
                            'id' => 'call_3',
                            'name' => 'lookup',
                            'response' => ['rawResponse' => 'ok'],
                        ]],
                    ]],
                ],
            ],
        ];

        yield 'system message lifts to system_instruction' => [
            [
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Hello!']]]],
                ],
            ],
            static fn ($result) => new MessageBag(
                Message::forSystem('You are friendly.'),
                Message::ofUser('Greet me.'),
                Message::ofAssistant($result),
                Message::ofUser('Again.'),
            ),
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Greet me.']]],
                    ['role' => 'model', 'parts' => [['text' => 'Hello!']]],
                    ['role' => 'user', 'parts' => [['text' => 'Again.']]],
                ],
                'system_instruction' => ['parts' => [['text' => 'You are friendly.']]],
            ],
        ];
    }
}
