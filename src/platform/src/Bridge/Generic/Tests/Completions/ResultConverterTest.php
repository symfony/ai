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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Completions\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RuntimeException;
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

        $httpLike = new class {
            public function getStatusCode(): int
            {
                return 200;
            }
        };

        $raw = new InMemoryRawResult([], $events, $httpLike);
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
}
