<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Gemini;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ResultConverter;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItConvertsAResponseToAVectorResult()
    {
        $payload = [
            'content' => ['parts' => [['text' => 'Hello, world!']]],
        ];
        $expectedResponse = [
            'candidates' => [$payload],
        ];
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn($expectedResponse);

        $resultConverter = new ResultConverter();

        $result = $resultConverter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, world!', $result->getContent());
    }

    public function testItThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getContent')->willReturn(json_encode([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'The input token count (1294145) exceeds the maximum number of tokens allowed (1048576).',
            ],
        ]));

        $resultConverter = new ResultConverter();

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('exceeds the maximum number of tokens allowed');

        $resultConverter->convert(new RawHttpResult($response));
    }

    public function testItReturnsAggregatedTextOnSuccess()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(__DIR__.'/Fixtures/code_execution_outcome_ok.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = [
            new TextResult("First text\n"),
            new ExecutableCodeResult("print('Hello, World!')", 'PYTHON'),
            new CodeExecutionResult(true, 'Hello, World!'),
            new TextResult("Second text\n"),
            new TextResult("Third text\n"),
            new TextResult('Fourth text'),
        ];

        $this->assertEquals($parts, $result->getContent());
        $this->assertEquals("First text\nSecond text\nThird text\nFourth text", $result->asText());
    }

    public function testItReturnsMultiPartIfMultipleContentPartsAreGiven()
    {
        $payload = [
            'content' => [
                'parts' => [
                    [
                        'text' => 'foo',
                    ],
                    [
                        'functionCall' => [
                            'name' => 'some_tool',
                            'args' => [],
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = [
            'candidates' => [$payload],
        ];
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn($expectedResponse);

        $resultConverter = new ResultConverter();

        $result = $resultConverter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertInstanceOf(ToolCallResult::class, $result->getContent()[1]);
        $toolCall = $result->getContent()[1]->getContent()[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('some_tool', $toolCall->getName());
    }

    public function testItDoesNotSucceedOnFailure()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(__DIR__.'/Fixtures/code_execution_outcome_failed.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = [
            new TextResult('First text'),
            new ExecutableCodeResult("print('Hello, World!')", 'PYTHON'),
            new CodeExecutionResult(false, 'An error occurred during code execution.'),
            new TextResult('Last text'),
        ];

        $this->assertEquals($parts, $result->getContent());
    }

    public function testItDoesNotSucceedOnTimeout()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(__DIR__.'/Fixtures/code_execution_outcome_deadline_exceeded.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = [
            new TextResult('First text'),
            new ExecutableCodeResult("print('Hello, World!')", 'PYTHON'),
            new CodeExecutionResult(false, 'An error occurred during code execution.'),
            new TextResult('Last text'),
        ];

        $this->assertEquals($parts, $result->getContent());
    }

    public function testConvertsThoughtPartToThinkingResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Reasoning step.', 'thought' => true, 'thoughtSignature' => 'sig_abc'],
                            ['text' => 'Final answer.'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('Reasoning step.', $parts[0]->getContent());
        $this->assertSame('sig_abc', $parts[0]->getSignature());
    }

    public function testConvertsSignedTextPartCarriesSignature()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                ['content' => ['parts' => [
                    ['text' => 'Signed visible text.', 'thoughtSignature' => 'sig_text'],
                ]]],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Signed visible text.', $result->getContent());
        $this->assertSame('sig_text', $result->getSignature());
    }

    public function testConvertsSignedFunctionCallCarriesSignature()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'run', 'args' => ['x' => 1]], 'thoughtSignature' => 'sig_call'],
                ]]],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('sig_call', $toolCalls[0]->getSignature());
    }

    public function testConvertReturnsEmptyTextResultForCandidateWithoutContentParts()
    {
        $response = $this->createStub(ResponseInterface::class);
        // Gemini occasionally returns a valid completion with a terminal finish reason but no
        // content parts, e.g. an empty message after a tool result.
        $response->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => ['role' => 'model'],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $result = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::STOP));
    }

    public function testConvertThrowsWhenResponseContainsNoCandidates()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'candidates' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testConvertCoercesEmptyStringToolCallArgumentsToNull()
    {
        $response = $this->createStub(ResponseInterface::class);
        // Gemini emits empty strings for optional object properties it has no value for; empty
        // strings inside list arguments are legitimate values and must be preserved.
        $response->method('toArray')->willReturn([
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'search', 'args' => [
                        'query' => 'Symfony',
                        'departureDate' => '',
                        'nested' => ['since' => ''],
                        'tags' => ['a', '', 'b'],
                    ]]],
                ]]],
            ],
        ]);

        $result = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $arguments = $result->getContent()[0]->getArguments();
        $this->assertSame('Symfony', $arguments['query']);
        $this->assertNull($arguments['departureDate']);
        $this->assertNull($arguments['nested']['since']);
        $this->assertSame(['a', '', 'b'], $arguments['tags']);
    }

    public function testConvertsInlineDataToBinaryResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'text/plain',
                                        'data' => 'SGVsbG8=',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $resultConverter = new ResultConverter();

        $result = $resultConverter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('Hello', $result->getContent());
        $this->assertSame('text/plain', $result->getMimeType());
    }

    public function testConvertsInlineDataWithoutMimeTypeToBinaryResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'data' => 'SGVsbG8=',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $resultConverter = new ResultConverter();

        $result = $resultConverter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('Hello', $result->getContent());
        $this->assertNull($result->getMimeType());
    }

    /**
     * @param array<string, mixed> $chunk
     */
    #[DataProvider('streamDeltaProvider')]
    public function testStreamingConvertsChoicesToDeltas(array $chunk, string $expectedClass)
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($response);
        $rawResult->method('getDataStream')->willReturn((static function () use ($chunk): \Generator {
            yield $chunk;
        })());

        $resultConverter = new ResultConverter();
        $result = $resultConverter->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $items = iterator_to_array($result->getContent());
        $this->assertCount(1, $items);
        $this->assertInstanceOf($expectedClass, $items[0]);

        if ($items[0] instanceof TextDelta) {
            $this->assertSame('Hello', $items[0]->getText());
        }

        if ($items[0] instanceof BinaryDelta) {
            $this->assertSame('Hello', $items[0]->getData());
            $this->assertSame('text/plain', $items[0]->getMimeType());
        }

        if ($items[0] instanceof ToolCallComplete) {
            $this->assertSame('some_tool', $items[0]->getToolCalls()[0]->getName());
        }

        if ($items[0] instanceof ChoiceDelta) {
            $this->assertCount(2, $items[0]->getDeltas());
            $this->assertInstanceOf(TextDelta::class, $items[0]->getDeltas()[0]);
            $this->assertInstanceOf(ToolCallComplete::class, $items[0]->getDeltas()[1]);
        }
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: class-string}>
     */
    public static function streamDeltaProvider(): iterable
    {
        yield 'text' => [[
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Hello']],
                ],
            ]],
        ], TextDelta::class];

        yield 'binary' => [[
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'inlineData' => [
                            'mimeType' => 'text/plain',
                            'data' => 'SGVsbG8=',
                        ],
                    ]],
                ],
            ]],
        ], BinaryDelta::class];

        yield 'tool call' => [[
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'functionCall' => [
                            'name' => 'some_tool',
                            'args' => [],
                        ],
                    ]],
                ],
            ]],
        ], ToolCallComplete::class];

        yield 'choice' => [[
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'Hello']],
                    ],
                ],
                [
                    'content' => [
                        'parts' => [[
                            'functionCall' => [
                                'name' => 'some_tool',
                                'args' => [],
                            ],
                        ]],
                    ],
                ],
            ],
        ], ChoiceDelta::class];
    }

    public function testStreamingYieldsTokenUsageWhenUsageMetadataIsPresent()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($response);
        $rawResult->method('getDataStream')->willReturn((static function (): \Generator {
            yield [
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Hello']]],
                ]],
            ];
            yield [
                'candidates' => [[
                    'content' => ['parts' => [['text' => ' world']]],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 15,
                    'candidatesTokenCount' => 25,
                    'thoughtsTokenCount' => 3,
                    'totalTokenCount' => 43,
                ],
            ];
        })());

        $result = (new ResultConverter())->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $items = iterator_to_array($result->getContent(), false);

        $this->assertCount(3, $items);
        $this->assertInstanceOf(TextDelta::class, $items[0]);
        $this->assertSame('Hello', $items[0]->getText());

        $this->assertInstanceOf(TokenUsageInterface::class, $items[1]);
        $this->assertSame(15, $items[1]->getPromptTokens());
        $this->assertSame(25, $items[1]->getCompletionTokens());
        $this->assertSame(3, $items[1]->getThinkingTokens());
        $this->assertSame(43, $items[1]->getTotalTokens());

        $this->assertInstanceOf(TextDelta::class, $items[2]);
        $this->assertSame(' world', $items[2]->getText());
    }

    public function testStreamingSkipsEmptyOrPartialUsageMetadataChunks()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($response);
        $rawResult->method('getDataStream')->willReturn((static function (): \Generator {
            yield [
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Hello']]],
                ]],
                'usageMetadata' => [],
            ];
            yield [
                'candidates' => [[
                    'content' => ['parts' => [['text' => ' world']]],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 15,
                ],
            ];
            yield [
                'candidates' => [[
                    'content' => ['parts' => [['text' => '!']]],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 15,
                    'candidatesTokenCount' => 25,
                    'totalTokenCount' => 40,
                ],
            ];
        })());

        $result = (new ResultConverter())->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $items = iterator_to_array($result->getContent(), false);

        $this->assertCount(4, $items);
        $this->assertInstanceOf(TextDelta::class, $items[0]);
        $this->assertSame('Hello', $items[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $items[1]);
        $this->assertSame(' world', $items[1]->getText());
        $this->assertInstanceOf(TokenUsageInterface::class, $items[2]);
        $this->assertSame(40, $items[2]->getTotalTokens());
        $this->assertInstanceOf(TextDelta::class, $items[3]);
        $this->assertSame('!', $items[3]->getText());
    }

    public function testStreamConvertsSingleThoughtPartToThinkingDelta()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($response);
        // A thinking-enabled model streams thought parts, which convertChoice() turns into a
        // ThinkingResult; the stream frames it with ThinkingStart and ThinkingComplete boundaries.
        $rawResult->method('getDataStream')->willReturn((static function (): \Generator {
            yield [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'Let me think.', 'thought' => true, 'thoughtSignature' => 'sig_1'],
                    ]]],
                ],
            ];
        })());

        $result = (new ResultConverter())->convert($rawResult, ['stream' => true]);
        $items = iterator_to_array($result->getContent());

        $this->assertCount(3, $items);
        $this->assertInstanceOf(ThinkingStart::class, $items[0]);
        $this->assertInstanceOf(ThinkingDelta::class, $items[1]);
        $this->assertSame('Let me think.', $items[1]->getThinking());
        $this->assertInstanceOf(ThinkingComplete::class, $items[2]);
        $this->assertSame('Let me think.', $items[2]->getThinking());
        $this->assertSame('sig_1', $items[2]->getSignature());
    }

    public function testStreamExpandsMultiPartCandidateIntoDeltas()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($response);
        // A candidate with several parts (thought + text) is a MultiPartResult; the thought is framed
        // with ThinkingStart / ThinkingComplete and the text follows as a TextDelta.
        $rawResult->method('getDataStream')->willReturn((static function (): \Generator {
            yield [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'Reasoning.', 'thought' => true],
                        ['text' => 'Final answer.'],
                    ]]],
                ],
            ];
        })());

        $result = (new ResultConverter())->convert($rawResult, ['stream' => true]);
        $items = iterator_to_array($result->getContent());

        $this->assertCount(4, $items);
        $this->assertInstanceOf(ThinkingStart::class, $items[0]);
        $this->assertInstanceOf(ThinkingDelta::class, $items[1]);
        $this->assertSame('Reasoning.', $items[1]->getThinking());
        $this->assertInstanceOf(ThinkingComplete::class, $items[2]);
        $this->assertSame('Reasoning.', $items[2]->getThinking());
        $this->assertInstanceOf(TextDelta::class, $items[3]);
        $this->assertSame('Final answer.', $items[3]->getText());
    }

    public function testStreamFramesThoughtPartsSplitAcrossChunksWithSingleBoundaryPair()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($response);
        // Gemini splits a thinking block across several chunks before the answer; the boundary logic
        // must emit a single ThinkingStart, one ThinkingDelta per chunk, and one ThinkingComplete
        // whose accumulated text is the concatenation, then the answer as a TextDelta.
        $rawResult->method('getDataStream')->willReturn((static function (): \Generator {
            yield [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'First thought. ', 'thought' => true],
                    ]]],
                ],
            ];
            yield [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'Second thought.', 'thought' => true, 'thoughtSignature' => 'sig_final'],
                    ]]],
                ],
            ];
            yield [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'The answer.'],
                    ]]],
                ],
            ];
        })());

        $result = (new ResultConverter())->convert($rawResult, ['stream' => true]);
        $items = iterator_to_array($result->getContent());

        $this->assertCount(5, $items);
        $this->assertInstanceOf(ThinkingStart::class, $items[0]);
        $this->assertInstanceOf(ThinkingDelta::class, $items[1]);
        $this->assertSame('First thought. ', $items[1]->getThinking());
        $this->assertInstanceOf(ThinkingDelta::class, $items[2]);
        $this->assertSame('Second thought.', $items[2]->getThinking());
        $this->assertInstanceOf(ThinkingComplete::class, $items[3]);
        $this->assertSame('First thought. Second thought.', $items[3]->getThinking());
        $this->assertSame('sig_final', $items[3]->getSignature());
        $this->assertInstanceOf(TextDelta::class, $items[4]);
        $this->assertSame('The answer.', $items[4]->getText());
    }

    public function testStreamExpandsToolCallWithTextIntoDeltas()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($response);
        $rawResult->method('getDataStream')->willReturn((static function (): \Generator {
            yield [
                'candidates' => [
                    ['content' => ['parts' => [
                        ['text' => 'Calling tool.'],
                        ['functionCall' => ['name' => 'search', 'args' => ['q' => 'x']]],
                    ]]],
                ],
            ];
        })());

        $result = (new ResultConverter())->convert($rawResult, ['stream' => true]);
        $items = iterator_to_array($result->getContent());

        $this->assertCount(2, $items);
        $this->assertInstanceOf(TextDelta::class, $items[0]);
        $this->assertSame('Calling tool.', $items[0]->getText());
        $this->assertInstanceOf(ToolCallComplete::class, $items[1]);
        $this->assertSame('search', $items[1]->getToolCalls()[0]->getName());
    }

    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(500);
        $httpResponse->method('getContent')->willReturn('Service Unavailable');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert(new RawHttpResult($httpResponse), ['stream' => true]);
    }
}
