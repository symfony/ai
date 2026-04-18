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
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
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
}
