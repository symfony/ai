<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Gemini\Gemini;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
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
        $httpResponse->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => 'image/png',
                                    'data' => 'base64EncodedImageData',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('base64EncodedImageData', $result->getContent());
        $this->assertSame('image/png', $result->getMimeType());
    }

    public function testConvertsInlineDataWithoutMimeTypeToBinaryResult()
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
                                'inlineData' => [
                                    'data' => 'base64EncodedData',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('base64EncodedData', $result->getContent());
        $this->assertNull($result->getMimeType());
    }
}
