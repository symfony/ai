<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskar@php.com>
 */
final class ResultConverterTest extends TestCase
{
    public function testConvertThrowsExceptionWithDetailedErrorInformation()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(403);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'code' => 403,
                'status' => 'PERMISSION_DENIED',
                'message' => 'The caller does not have permission.',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "403" - "PERMISSION_DENIED": "The caller does not have permission.".');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'The input token count (1294145) exceeds the maximum number of tokens allowed (1048576).',
            ],
        ]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('exceeds the maximum number of tokens allowed');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testReturnsMultiPartIfMultipleContentPartsAreGiven()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => 'foo',
                            ],
                            [
                                'functionCall' => [
                                    'id' => '1234',
                                    'name' => 'some_tool',
                                    'args' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertInstanceOf(ToolCallResult::class, $result->getContent()[1]);
        $toolCall = $result->getContent()[1]->getContent()[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('1234', $toolCall->getId());
    }

    public function testConvertExposesFinishReasonAsMetadata()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => ['parts' => [['text' => 'Truncated']]],
                    'finishReason' => 'MAX_TOKENS',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertTrue($finishReason->is(FinishReasonCase::LENGTH));
        $this->assertSame('MAX_TOKENS', $finishReason->getRaw());
    }

    public function testConvertNormalizesGeminiSpecificFinishReasonToOther()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => ['parts' => [['text' => 'Quoted']]],
                    'finishReason' => 'RECITATION',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertTrue($finishReason->is(FinishReasonCase::OTHER));
        $this->assertSame('RECITATION', $finishReason->getRaw());
    }

    public function testConvertsInlineDataToBinaryResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $image = Image::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg');
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => 'image/jpeg',
                                    'data' => $image->asBase64(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame($image->asBinary(), $result->getContent());
        $this->assertSame('image/jpeg', $result->getMimeType());
        $this->assertSame($image->asDataUrl(), $result->toDataUri());
    }

    public function testConvertsInlineDataWithoutMimeTypeToBinaryResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $image = Image::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg');
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'data' => $image->asBase64(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame($image->asBinary(), $result->getContent());
        $this->assertNull($result->getMimeType());
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
                    ['functionCall' => ['id' => 'id1', 'name' => 'run', 'args' => ['x' => 1]], 'thoughtSignature' => 'sig_call'],
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
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        // Gemini occasionally returns a valid completion with a terminal finish reason but no
        // content parts, e.g. an empty message after a tool result.
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => ['role' => 'model'],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::STOP));
    }

    public function testConvertThrowsWhenResponseContainsNoCandidates()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertCoercesEmptyStringToolCallArgumentsToNull()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        // Gemini emits empty strings for optional object properties it has no value for; empty
        // strings inside list arguments are legitimate values and must be preserved.
        $httpResponse->method('toArray')->willReturn([
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

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $arguments = $result->getContent()[0]->getArguments();
        $this->assertSame('Symfony', $arguments['query']);
        $this->assertNull($arguments['departureDate']);
        $this->assertNull($arguments['nested']['since']);
        $this->assertSame(['a', '', 'b'], $arguments['tags']);
    }

    public function testStreamConvertsSingleThoughtPartToThinkingDelta()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($httpResponse);
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

        $result = $converter->convert($rawResult, ['stream' => true]);
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
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($httpResponse);
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

        $result = $converter->convert($rawResult, ['stream' => true]);
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
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($httpResponse);
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

        $result = $converter->convert($rawResult, ['stream' => true]);
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
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($httpResponse);
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

        $result = $converter->convert($rawResult, ['stream' => true]);
        $items = iterator_to_array($result->getContent());

        $this->assertCount(2, $items);
        $this->assertInstanceOf(TextDelta::class, $items[0]);
        $this->assertSame('Calling tool.', $items[0]->getText());
        $this->assertInstanceOf(ToolCallComplete::class, $items[1]);
        $this->assertSame('search', $items[1]->getToolCalls()[0]->getName());
    }

    public function testStreamSkipsCandidatesWithoutContentParts()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($httpResponse);
        $rawResult->method('getDataStream')->willReturn(
            (static function (): \Generator {
                yield [
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Hello'],
                                ],
                            ],
                        ],
                    ],
                ];
                yield [
                    'candidates' => [
                        [
                            'finishReason' => 'STOP',
                        ],
                    ],
                ];
                yield [
                    'usageMetadata' => [
                        'promptTokenCount' => 10,
                        'candidatesTokenCount' => 5,
                    ],
                ];
            })(),
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $items = iterator_to_array($result->getContent());
        $this->assertCount(2, $items);
        $this->assertInstanceOf(TextDelta::class, $items[0]);
        $this->assertSame('Hello', $items[0]->getText());

        // The content-less candidate is skipped, but still contributes its finish reason.
        $this->assertInstanceOf(MetadataDelta::class, $items[1]);
        $this->assertSame('finish_reason', $items[1]->getKey());
        $this->assertTrue($items[1]->getValue()->is(FinishReasonCase::STOP));
        $this->assertSame('STOP', $items[1]->getValue()->getRaw());
    }

    /**
     * @param array<string, mixed> $chunk
     * @param array<string, mixed> $expectedPayload
     */
    #[DataProvider('streamDeltaProvider')]
    public function testStreamConvertsChoicesToDeltas(array $chunk, string $expectedClass, array $expectedPayload)
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getObject')->willReturn($httpResponse);
        $rawResult->method('getDataStream')->willReturn((static function () use ($chunk): \Generator {
            yield $chunk;
        })());

        $result = $converter->convert($rawResult, ['stream' => true]);
        $items = iterator_to_array($result->getContent());

        $this->assertCount(1, $items);
        $this->assertInstanceOf($expectedClass, $items[0]);

        if (TextDelta::class === $expectedClass) {
            $this->assertSame($expectedPayload['text'], $items[0]->getText());

            return;
        }

        if (BinaryDelta::class === $expectedClass) {
            $this->assertSame($expectedPayload['data'], $items[0]->getData());
            $this->assertSame($expectedPayload['mimeType'], $items[0]->getMimeType());

            return;
        }

        if (ToolCallComplete::class === $expectedClass) {
            $this->assertSame($expectedPayload['id'], $items[0]->getToolCalls()[0]->getId());
            $this->assertSame($expectedPayload['name'], $items[0]->getToolCalls()[0]->getName());

            return;
        }

        if (ChoiceDelta::class === $expectedClass) {
            $this->assertCount(2, $items[0]->getDeltas());
            $this->assertInstanceOf(TextDelta::class, $items[0]->getDeltas()[0]);
            $this->assertInstanceOf(ToolCallComplete::class, $items[0]->getDeltas()[1]);

            return;
        }

        $this->fail(\sprintf('Unexpected expected class "%s".', $expectedClass));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: class-string, 2: array<string, mixed>}>
     */
    public static function streamDeltaProvider(): iterable
    {
        yield 'text' => [[
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Hello']],
                ],
            ]],
        ], TextDelta::class, ['text' => 'Hello']];

        yield 'binary' => [[
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'inlineData' => [
                            'data' => 'SGVsbG8=',
                            'mimeType' => 'text/plain',
                        ],
                    ]],
                ],
            ]],
        ], BinaryDelta::class, ['data' => 'Hello', 'mimeType' => 'text/plain']];

        yield 'tool call' => [[
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'functionCall' => [
                            'id' => 'call_1',
                            'name' => 'tool',
                            'args' => [],
                        ],
                    ]],
                ],
            ]],
        ], ToolCallComplete::class, ['id' => 'call_1', 'name' => 'tool']];

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
                                'id' => 'call_1',
                                'name' => 'tool',
                                'args' => [],
                            ],
                        ]],
                    ],
                ],
            ],
        ], ChoiceDelta::class, []];
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
