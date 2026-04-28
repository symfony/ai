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
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
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
}
