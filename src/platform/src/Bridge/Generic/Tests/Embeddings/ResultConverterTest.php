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
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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
        $httpResponse->method('getContent')->willReturn('{"error":{"message":"You exceeded your current quota, please check your plan and billing details."}}');

        $exception = null;
        try {
            (new ResultConverter())->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(60, $exception->getRetryAfter());
        $this->assertSame('Rate limit exceeded. You exceeded your current quota, please check your plan and billing details.', $exception->getMessage());
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
        $this->assertSame('Rate limit exceeded.', $exception->getMessage());
    }

    public function testThrowsServerExceptionOnServerError()
    {
        $response = $this->createResponse('{"error":{"message":"Service unavailable."}}', 503);

        $exception = null;
        try {
            (new ResultConverter())->convert(new RawHttpResult($response));
        } catch (ServerException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(503, $exception->getStatusCode());
        $this->assertSame('Server error (HTTP 503). Service unavailable.', $exception->getMessage());
    }

    public function testThrowsServerExceptionOnServerErrorWithoutJsonBody()
    {
        $response = $this->createResponse('<html>Bad Gateway</html>', 502);

        $exception = null;
        try {
            (new ResultConverter())->convert(new RawHttpResult($response));
        } catch (ServerException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(502, $exception->getStatusCode());
        $this->assertSame('Server error (HTTP 502).', $exception->getMessage());
    }

    public function testThrowsAuthenticationExceptionWithoutJsonBody()
    {
        $response = $this->createResponse('Unauthorized', 401);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed.');

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    private function createResponse(string $body, int $statusCode): ResponseInterface
    {
        return (new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode])))->request('POST', 'https://api.example.com/v1/embeddings');
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
