<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings\ResultConverter;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ResultConverterTest extends TestCase
{
    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(json_decode($this->getEmbeddingStub(), true));

        $vectorResult = (new ResultConverter())->convert(new RawHttpResult($result));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testThrowsExceedContextSizeExceptionOnATooLongInput()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage("Invalid 'input': maximum context length is 8192 tokens.");

        (new ResultConverter())->convert(new RawHttpResult($this->badRequest("Invalid 'input': maximum context length is 8192 tokens.")));
    }

    public function testThrowsBadRequestExceptionOnOtherBadRequests()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("'$.input' is invalid.");

        (new ResultConverter())->convert(new RawHttpResult($this->badRequest("'$.input' is invalid.")));
    }

    private function badRequest(string $message): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('toArray')->willReturn([
            'error' => ['message' => $message, 'type' => 'invalid_request_error', 'param' => null, 'code' => null],
        ]);

        return $response;
    }

    private function getEmbeddingStub(): string
    {
        return <<<'JSON'
            {
              "object": "list",
              "data": [
                {
                  "object": "embedding",
                  "index": 0,
                  "embedding": [0.3, 0.4, 0.4]
                },
                {
                  "object": "embedding",
                  "index": 1,
                  "embedding": [0.0, 0.0, 0.2]
                }
              ]
            }
            JSON;
    }
}
