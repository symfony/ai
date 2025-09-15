<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\HttpErrorHandler;
use Symfony\AI\Platform\Exception\NotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServiceUnavailableException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HttpErrorHandler::class)]
class HttpErrorHandlerTest extends TestCase
{
    public function testHandleHttpErrorWithSuccessfulResponse()
    {
        $mockResponse = new MockResponse('{"success": true}', ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectNotToPerformAssertions();
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleAuthenticationError()
    {
        $mockResponse = new MockResponse(
            '{"error": {"message": "Invalid API key"}}',
            ['http_code' => 401]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleNotFoundError()
    {
        $mockResponse = new MockResponse(
            '{"error": {"message": "Model not found"}}',
            ['http_code' => 404]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Model not found');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleServiceUnavailableError()
    {
        $mockResponse = new MockResponse(
            '{"error": {"message": "Service temporarily unavailable"}}',
            ['http_code' => 503]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Service temporarily unavailable');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleGenericClientError()
    {
        $mockResponse = new MockResponse(
            '{"error": {"message": "Bad request"}}',
            ['http_code' => 400]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Bad request');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithDifferentMessageFormats()
    {
        $mockResponse = new MockResponse(
            '{"error": "Direct error message"}',
            ['http_code' => 400]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Direct error message');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithMessageField()
    {
        $mockResponse = new MockResponse(
            '{"message": "Simple message format"}',
            ['http_code' => 400]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Simple message format');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithDetailField()
    {
        $mockResponse = new MockResponse(
            '{"detail": "Detailed error information"}',
            ['http_code' => 400]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Detailed error information');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithInvalidJson()
    {
        $mockResponse = new MockResponse(
            'Plain text error message',
            ['http_code' => 500]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500: Plain text error message');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithEmptyResponse()
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500: HTTP 500 error');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleRateLimitWithRetryAfterHeader()
    {
        $mockResponse = new MockResponse(
            '{"error": "Rate limit exceeded"}',
            ['http_code' => 429, 'response_headers' => ['Retry-After' => ['60']]]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        try {
            HttpErrorHandler::handleHttpError($response);
            $this->fail('Expected RateLimitExceededException was not thrown');
        } catch (RateLimitExceededException $e) {
            $this->assertEquals(60.0, $e->getRetryAfter());
        }
    }

    public function testHandleRateLimitWithoutRetryAfterHeader()
    {
        $mockResponse = new MockResponse(
            '{"error": "Rate limit exceeded"}',
            ['http_code' => 429]
        );
        $client = new MockHttpClient($mockResponse);
        $response = $client->request('GET', 'https://example.com');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded.');
        HttpErrorHandler::handleHttpError($response);
    }
}
