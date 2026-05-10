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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Hello world',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertToolCallResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'function_call',
                    'id' => 'call_123',
                    'name' => 'test_function',
                    'arguments' => '{"arg1": "value1"}',
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

    public function testConvertMultipleMessagesIntoMultiPartResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'role' => 'assistant',
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Part 1',
                    ]],
                ],
                [
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Part 2',
                    ]],
                    'type' => 'message',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $output = $result->getContent();
        $this->assertCount(2, $output);
        $this->assertSame('Part 1', $output[0]->getContent());
        $this->assertSame('Part 2', $output[1]->getContent());
    }

    public function testConvertReasoningPlusMessageIntoMultiPartResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [
                        ['type' => 'summary_text', 'text' => 'Let me work through this.'],
                    ],
                ],
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => '{"answer": 42}',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('Let me work through this.', $parts[0]->getContent());
        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('{"answer": 42}', $parts[1]->getContent());
    }

    public function testConvertReasoningEmitsOneThinkingResultPerSummaryChunk()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [
                        ['type' => 'summary_text', 'text' => 'First, I subtract 7.'],
                        ['type' => 'summary_text', 'text' => 'Then I divide by 8.'],
                    ],
                ],
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'x = -3.75',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(3, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('First, I subtract 7.', $parts[0]->getContent());
        $this->assertInstanceOf(ThinkingResult::class, $parts[1]);
        $this->assertSame('Then I divide by 8.', $parts[1]->getContent());
        $this->assertInstanceOf(TextResult::class, $parts[2]);
        $this->assertSame('x = -3.75', $parts[2]->getContent());
    }

    public function testConvertReasoningWithoutSummaryIsDropped()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [],
                ],
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'final',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('final', $result->getContent());
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
                'message' => 'Invalid API key provided',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionWhenNoOutput()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output');

        $converter->convert(new RawHttpResult($httpResponse));
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

    public function testThrowsRateLimitExceededExceptionOn429()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(429);
        $httpResponse->method('getContent')->willReturn('{"error":{"message":"You exceeded your current quota, please check your plan and billing details."}}');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. You exceeded your current quota, please check your plan and billing details.');

        $converter->convert(new RawHttpResult($httpResponse));
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
                'message' => 'The model `unknown` does not exist',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "invalid_request_error"-invalid_request (model): "The model `unknown` does not exist".');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testStreamTransmitsUsageToResultMetadata()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'message.delta.output_text.delta',
                'delta' => 'Hello',
            ],
            [
                'type' => 'message.delta.output_text.delta',
                'delta' => ' world',
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'usage' => [
                        'input_tokens' => 11,
                        'output_tokens' => 7,
                        'output_tokens_details' => [
                            'reasoning_tokens' => 2,
                        ],
                        'input_tokens_details' => [
                            'cached_tokens' => 3,
                        ],
                        'total_tokens' => 18,
                    ],
                    'output' => [],
                ],
            ],
        ];

        $raw = new class($httpResponse, $events) implements RawResultInterface {
            /**
             * @param array<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }

            public function getObject(): object
            {
                return $this->response;
            }
        };

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame(' world', $chunks[1]->getText());

        $this->assertInstanceOf(TokenUsage::class, $chunks[2]);
        $this->assertSame(11, $chunks[2]->getPromptTokens());
        $this->assertSame(7, $chunks[2]->getCompletionTokens());
        $this->assertSame(2, $chunks[2]->getThinkingTokens());
        $this->assertSame(3, $chunks[2]->getCachedTokens());
        $this->assertSame(18, $chunks[2]->getTotalTokens());
    }

    public function testStreamWithToolCalls()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [
                        [
                            'type' => 'function_call',
                            'id' => 'call_456',
                            'name' => 'get_weather',
                            'arguments' => '{"city": "Berlin"}',
                        ],
                    ],
                ],
            ],
        ];

        $raw = new class($httpResponse, $events) implements RawResultInterface {
            /**
             * @param array<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }

            public function getObject(): object
            {
                return $this->response;
            }
        };

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_456', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testStreamWithReasoningContent()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.reasoning_summary_text.delta',
                'delta' => 'Let me think',
            ],
            [
                'type' => 'response.reasoning_summary_text.delta',
                'delta' => ' about this...',
            ],
            [
                'type' => 'response.reasoning_summary_text.done',
            ],
            [
                'type' => 'response.output_text.delta',
                'delta' => 'The answer is 42.',
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [],
                ],
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(5, $chunks);
        $this->assertInstanceOf(ThinkingStart::class, $chunks[0]);
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[1]);
        $this->assertSame('Let me think', $chunks[1]->getThinking());
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[2]);
        $this->assertSame(' about this...', $chunks[2]->getThinking());
        $this->assertInstanceOf(ThinkingComplete::class, $chunks[3]);
        $this->assertSame('Let me think about this...', $chunks[3]->getThinking());
        $this->assertInstanceOf(TextDelta::class, $chunks[4]);
        $this->assertSame('The answer is 42.', $chunks[4]->getText());
    }
}
