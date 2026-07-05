<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests\Completions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Completions\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ResultConverterTest extends TestCase
{
    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello world',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertToolWithArgsCallResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                    'arguments' => '{"arg1": "value1"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
    }

    public function testConvertToolWithEmptyArgsCallResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                    'arguments' => '',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testConvertToolWithoutArgsCallResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testConvertMultipleChoices()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 1',
                    ],
                    'finish_reason' => 'stop',
                ],
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 2',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ChoiceResult::class, $result);
        $choices = $result->getContent();
        $this->assertCount(2, $choices);
        $this->assertSame('Choice 1', $choices[0]->getContent());
        $this->assertSame('Choice 2', $choices[1]->getContent());
    }

    public function testContentFilterException()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);

        $httpResponse->expects($this->exactly(1))
            ->method('toArray')
            ->willReturnCallback(static function ($throw = true) {
                if ($throw) {
                    throw new class extends \Exception implements ClientExceptionInterface {
                        public function getResponse(): ResponseInterface
                        {
                            throw new RuntimeException('Not implemented');
                        }
                    };
                }

                return [
                    'error' => [
                        'code' => 'content_filter',
                        'message' => 'Content was filtered',
                    ],
                ];
            });

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content was filtered');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsAuthenticationExceptionOnInvalidApiKey()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Invalid API key provided: sk-invalid',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided: sk-invalid');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionWhenNoChoices()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain choices');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionForUnsupportedFinishReason()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test content',
                    ],
                    'finish_reason' => 'unsupported_reason',
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "unsupported_reason"');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array{message: string, code?: string|int} $error
     */
    #[DataProvider('provideContextOverflowErrors')]
    public function testThrowsExceedContextSizeExceptionOnContextOverflow(array $error)
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode(['error' => $error]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage($error['message']);

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @return iterable<string, array{array{message: string, code?: string|int}}>
     */
    public static function provideContextOverflowErrors(): iterable
    {
        yield 'error code' => [['message' => "This model's maximum context length is 128000 tokens.", 'code' => 'context_length_exceeded']];
        yield 'snake_case code in message' => [['message' => 'Error: context_length_exceeded', 'code' => 400]];
        yield 'spaced code variant in message' => [['message' => 'Context length exceeded for this request.']];
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponse()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Bad Request: invalid parameters',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request: invalid parameters');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponseWithNoResponseBody()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsOnUnhandledErrorStatusBeforeStreaming()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(404);
        $httpResponse->method('getContent')->willReturn('404 page not found');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 404: "404 page not found"');

        $converter->convert(new RawHttpResult($httpResponse), ['stream' => true]);
    }

    public function testThrowsServerExceptionOnServerErrorStatus()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(503);
        $httpResponse->method('getContent')->willReturn('{"error":{"message":"service unavailable"}}');

        try {
            $converter->convert(new RawHttpResult($httpResponse), ['stream' => true]);
            $this->fail('Expected a ServerException to be thrown.');
        } catch (ServerException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('service unavailable', $e->getMessage());
        }
    }

    public function testThrowsDetailedErrorException()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'code' => 'invalid_request_error',
                'type' => 'invalid_request',
                'param' => 'model',
                'message' => 'The model `gpt-5` does not exist',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "invalid_request_error"-invalid_request (model): "The model `gpt-5` does not exist".');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testStreamingInterleavedReasoningContentAndToolCalls()
    {
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'I need to check the weather']]]],
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'Let me call the tool']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Let me check']]]],
            ['choices' => [['index' => 0, 'delta' => [
                'tool_calls' => [
                    [
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_weather',
                            'arguments' => '',
                        ],
                    ],
                ],
            ]]]],
            ['choices' => [['index' => 0, 'delta' => [
                'tool_calls' => [
                    [
                        'function' => [
                            'arguments' => '{"city":"Beijing"}',
                        ],
                    ],
                ],
            ]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ];

        $raw = new InMemoryRawResult([], $events, $this->httpResponseStub());
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $thinkingDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('I need to check the weather', $thinkingDeltas[0]->getThinking());
        $this->assertSame('Let me call the tool', $thinkingDeltas[1]->getThinking());

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('I need to check the weatherLet me call the tool', $thinkingCompletes[0]->getThinking());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(1, $textDeltas);
        $this->assertSame('Let me check', $textDeltas[0]->getText());

        $toolCallStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallStart));
        $this->assertCount(1, $toolCallStarts);
        $this->assertSame('call_1', $toolCallStarts[0]->getId());
        $this->assertSame('get_weather', $toolCallStarts[0]->getName());

        $toolInputDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolInputDelta));
        $this->assertCount(1, $toolInputDeltas);
        $this->assertSame('call_1', $toolInputDeltas[0]->getId());
        $this->assertSame('get_weather', $toolInputDeltas[0]->getName());
        $this->assertSame('{"city":"Beijing"}', $toolInputDeltas[0]->getPartialJson());

        $toolCallCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallComplete));
        $this->assertCount(1, $toolCallCompletes);
        $completed = $toolCallCompletes[0]->getToolCalls();
        $this->assertCount(1, $completed);
        $this->assertSame('call_1', $completed[0]->getId());
        $this->assertSame('get_weather', $completed[0]->getName());
        $this->assertSame(['city' => 'Beijing'], $completed[0]->getArguments());
    }

    public function testStreamingToolCallsWithEmptyStringIdOnContinuationChunks()
    {
        // Some OpenAI-compatible providers (e.g. Alibaba Cloud Qwen / DashScope) send the tool-call
        // id ONLY on the first delta as a real value and then repeat it as an EMPTY STRING on every
        // continuation chunk (OpenAI itself omits the key entirely). `isset()` is true for "", so a
        // start must be keyed on a NON-EMPTY id — otherwise each continuation is misread as a new
        // tool-call start, the name is read from a delta that has none (Undefined array key "name"),
        // and the accumulated arguments are clobbered.
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'index' => 0, 'function' => ['name' => 'get_weather', 'arguments' => '']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['id' => '', 'type' => 'function', 'index' => 0, 'function' => ['arguments' => '{"city":']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['id' => '', 'type' => 'function', 'index' => 0, 'function' => ['arguments' => '"Beijing"}']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $toolCallStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallStart));
        $this->assertCount(1, $toolCallStarts, 'an empty-string id on continuation chunks must not start a new tool call');
        $this->assertSame('call_1', $toolCallStarts[0]->getId());
        $this->assertSame('get_weather', $toolCallStarts[0]->getName());

        $toolCallCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallComplete));
        $this->assertCount(1, $toolCallCompletes);
        $completed = $toolCallCompletes[0]->getToolCalls();
        $this->assertCount(1, $completed);
        $this->assertSame('call_1', $completed[0]->getId());
        $this->assertSame('get_weather', $completed[0]->getName());
        $this->assertSame(['city' => 'Beijing'], $completed[0]->getArguments());
    }

    public function testStreamingParallelToolCallsWithProviderIndexOnSingleElementChunks()
    {
        // OpenAI-compatible streams often send one tool_calls[] entry per chunk; the real slot is
        // tool_calls[].index, not the PHP array key (always 0). Without index-based correlation,
        // parallel tool calls collapse into a single entry (symfony/ai#2193).
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 0, 'id' => 'call_a', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 0, 'function' => ['arguments' => '{"city":']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 0, 'function' => ['arguments' => '"Paris"}']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 1, 'id' => 'call_b', 'type' => 'function', 'function' => ['name' => 'get_time', 'arguments' => '']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 1, 'function' => ['arguments' => '{"tz":']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['tool_calls' => [
                ['index' => 1, 'function' => ['arguments' => '"CET"}']],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $toolCallStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallStart));
        $this->assertCount(2, $toolCallStarts);
        $this->assertSame('call_a', $toolCallStarts[0]->getId());
        $this->assertSame('get_weather', $toolCallStarts[0]->getName());
        $this->assertSame('call_b', $toolCallStarts[1]->getId());
        $this->assertSame('get_time', $toolCallStarts[1]->getName());

        $toolInputDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolInputDelta));
        $this->assertCount(4, $toolInputDeltas);
        $this->assertSame('call_a', $toolInputDeltas[0]->getId());
        $this->assertSame('{"city":', $toolInputDeltas[0]->getPartialJson());
        $this->assertSame('call_a', $toolInputDeltas[1]->getId());
        $this->assertSame('"Paris"}', $toolInputDeltas[1]->getPartialJson());
        $this->assertSame('call_b', $toolInputDeltas[2]->getId());
        $this->assertSame('{"tz":', $toolInputDeltas[2]->getPartialJson());
        $this->assertSame('call_b', $toolInputDeltas[3]->getId());
        $this->assertSame('"CET"}', $toolInputDeltas[3]->getPartialJson());

        $toolCallCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ToolCallComplete));
        $this->assertCount(1, $toolCallCompletes);
        $completed = $toolCallCompletes[0]->getToolCalls();
        $this->assertCount(2, $completed);

        $byId = [];
        foreach ($completed as $toolCall) {
            $byId[$toolCall->getId()] = $toolCall;
        }

        $this->assertArrayHasKey('call_a', $byId);
        $this->assertSame('get_weather', $byId['call_a']->getName());
        $this->assertSame(['city' => 'Paris'], $byId['call_a']->getArguments());

        $this->assertArrayHasKey('call_b', $byId);
        $this->assertSame('get_time', $byId['call_b']->getName());
        $this->assertSame(['tz' => 'CET'], $byId['call_b']->getArguments());
    }

    public function testStreamingThrowsWhenFinishReasonIsMissing()
    {
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!']]]],
            // stream cut off: no terminal chunk carrying a non-null finish_reason
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Completions stream ended before a finish reason was received.');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamingDoesNotThrowWhenFinishReasonIsPresent()
    {
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(2, $chunks);
        $this->assertContainsOnlyInstancesOf(TextDelta::class, $chunks);
    }

    public function testStreamingDoesNotThrowWithUsageOnlyFinalChunk()
    {
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hi']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
            ['choices' => [], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $textDeltas = array_values(array_filter(iterator_to_array($streamResult->getContent()), static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(1, $textDeltas);
        $this->assertSame('Hi', $textDeltas[0]->getText());
    }

    public function testStreamingDoesNotThrowOnEmptyStream()
    {
        $converter = new ResultConverter();

        $streamResult = $converter->convert(new InMemoryRawResult([], [], $this->httpResponseStub()), ['stream' => true]);

        $this->assertSame([], iterator_to_array($streamResult->getContent()));
    }

    public function testStreamingThrowsOnTopLevelErrorEvent()
    {
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'partial']]]],
            ['error' => ['message' => 'Invalid model', 'code' => 'invalid_request_error']],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream error: "Invalid model".');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamingThrowsServerExceptionOnServerErrorEvent()
    {
        $converter = new ResultConverter();

        $streamResult = $converter->convert(new InMemoryRawResult([], [[
            'error' => ['message' => 'Provider exploded mid-stream', 'code' => 'server_error'],
        ]], $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error. Stream error: "Provider exploded mid-stream".');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamingThrowsRateLimitExceptionOnRateLimitEvent()
    {
        $converter = new ResultConverter();

        $streamResult = $converter->convert(new InMemoryRawResult([], [[
            'error' => ['message' => 'Too many requests', 'code' => 'rate_limit_error'],
        ]], $this->httpResponseStub()), ['stream' => true]);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Stream error: "Too many requests".');

        iterator_to_array($streamResult->getContent());
    }

    private function httpResponseStub(): object
    {
        return new class {
            public function getStatusCode(): int
            {
                return 200;
            }
        };
    }
}
