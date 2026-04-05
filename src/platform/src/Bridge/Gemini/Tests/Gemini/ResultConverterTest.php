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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
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
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'Invalid request: The model does not support this feature.',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "400" - "INVALID_ARGUMENT": "Invalid request: The model does not support this feature.".');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testReturnsToolCallEvenIfMultipleContentPartsAreGiven()
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
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $toolCall = $result->getContent()[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('1234', $toolCall->getId());
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
        $this->assertCount(1, $items);
        $this->assertInstanceOf(TextDelta::class, $items[0]);
        $this->assertSame('Hello', $items[0]->getText());
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
}
