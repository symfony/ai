<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi\Gemini;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ResultConverter;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
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
        $responseContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/Bridge/VertexAi/code_execution_outcome_ok.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(TextResult::class, $result);

        $this->assertEquals("Second text\nThird text\nFourth text", $result->getContent());
    }

    public function testItReturnsToolCallEvenIfMultipleContentPartsAreGiven()
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

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $toolCall = $result->getContent()[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('some_tool', $toolCall->getId());
    }

    public function testItThrowsExceptionOnFailure()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/Bridge/VertexAi/code_execution_outcome_failed.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $this->expectException(\RuntimeException::class);
        $converter->convert(new RawHttpResult($response));
    }

    public function testItThrowsExceptionOnTimeout()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/Bridge/VertexAi/code_execution_outcome_deadline_exceeded.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $this->expectException(\RuntimeException::class);
        $converter->convert(new RawHttpResult($response));
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
                                        'mimeType' => 'image/png',
                                        'data' => 'base64EncodedImageData',
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
        $this->assertSame('base64EncodedImageData', $result->getContent());
        $this->assertSame('image/png', $result->getMimeType());
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
                                        'data' => 'base64EncodedData',
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
        $this->assertSame('base64EncodedData', $result->getContent());
        $this->assertNull($result->getMimeType());
    }
}
