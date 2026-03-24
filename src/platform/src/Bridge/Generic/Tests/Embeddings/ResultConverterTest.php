<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Embeddings\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
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

    public function testThrowsRateLimitExceededExceptionWithRetryAfterHeader()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(429);
        $httpResponse->method('getHeaders')->willReturn(['retry-after' => ['60']]);

        $exception = null;
        try {
            (new ResultConverter())->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(60, $exception->getRetryAfter());
    }

    public function testThrowsRateLimitExceededExceptionWithoutRetryAfterHeader()
    {
        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(429);
        $httpResponse->method('getHeaders')->willReturn([]);

        $exception = null;
        try {
            (new ResultConverter())->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertNull($exception->getRetryAfter());
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
