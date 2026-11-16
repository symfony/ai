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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Embeddings\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
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

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideTopLevelErrorMessages')]
    public function testThrowsWithTheErrorMessageFromATopLevelMessage(int $statusCode, string $message, string $exception)
    {
        $response = $this->createResponse(json_encode(['message' => $message, 'type' => 'invalid_request_error']), $statusCode);

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    /**
     * @return iterable<string, array{int, string, class-string<\Throwable>}>
     */
    public static function provideTopLevelErrorMessages(): iterable
    {
        yield 'unauthorized' => [401, 'Invalid credentials in Authorization header', AuthenticationException::class];
        yield 'bad request' => [400, 'Input must not be empty', BadRequestException::class];
        yield 'not found' => [404, 'Model "some-embeddings" not found', BadRequestException::class];
        yield 'rate limit' => [429, 'Rate limit reached, try again later', RateLimitExceededException::class];
        yield 'server error' => [503, 'Model is overloaded', ServerException::class];
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideNonStringErrorMessages')]
    public function testFallsBackToTheDefaultMessageOnANonStringErrorMessage(int $statusCode, string $body, string $exception, string $message)
    {
        $response = $this->createResponse($body, $statusCode);

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    /**
     * @return iterable<string, array{int, string, class-string<\Throwable>, string}>
     */
    public static function provideNonStringErrorMessages(): iterable
    {
        yield 'unauthorized' => [401, '{"message":["a","b"]}', AuthenticationException::class, 'Authentication failed.'];
        yield 'bad request' => [400, '{"message":{"detail":[{"msg":"x"}]}}', BadRequestException::class, 'Bad Request'];
        yield 'not found' => [404, '{"error":{"message":["a","b"]}}', BadRequestException::class, 'Bad Request'];
        yield 'rate limit' => [429, '{"message":["a","b"]}', RateLimitExceededException::class, 'Rate limit exceeded.'];
        yield 'server error' => [503, '{"error":{"message":{"detail":[{"msg":"x"}]}}}', ServerException::class, 'Server error (HTTP 503).'];
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
