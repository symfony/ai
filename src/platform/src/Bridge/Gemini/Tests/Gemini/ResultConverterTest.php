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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
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
        $httpResponse = self::createMock(ResponseInterface::class);
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
        $httpResponse = self::createMock(ResponseInterface::class);
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
        $httpResponse = self::createMock(ResponseInterface::class);
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
        $httpResponse = self::createMock(ResponseInterface::class);
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

        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $rawResult = self::createMock(RawResultInterface::class);
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
        $this->assertSame('Hello', $items[0]);
    }
}
