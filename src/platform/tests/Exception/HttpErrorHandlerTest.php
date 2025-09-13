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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServiceUnavailableException;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HttpErrorHandler::class)]
class HttpErrorHandlerTest extends TestCase
{
    public function testHandleHttpErrorWithSuccessfulResponse():
    {
        $response = new MockResponse('{"success": true}', ['http_code' => 200]);

        $this->expectNotToPerformAssertions();
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleAuthenticationError():
    {
        $response = new MockResponse(
            '{"error": {"message": "Invalid API key"}}',
            ['http_code' => 401]
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleNotFoundError():
    {
        $response = new MockResponse(
            '{"error": {"message": "Model not found"}}',
            ['http_code' => 404]
        );

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Model not found');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleServiceUnavailableError():
    {
        $response = new MockResponse(
            '{"error": {"message": "Service temporarily unavailable"}}',
            ['http_code' => 503]
        );

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Service temporarily unavailable');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleGenericClientError():
    {
        $response = new MockResponse(
            '{"error": {"message": "Bad request"}}',
            ['http_code' => 400]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Bad request');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithDifferentMessageFormats():
    {
        $response = new MockResponse(
            '{"error": "Direct error message"}',
            ['http_code' => 400]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Direct error message');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithMessageField():
    {
        $response = new MockResponse(
            '{"message": "Simple message format"}',
            ['http_code' => 400]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Simple message format');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithDetailField():
    {
        $response = new MockResponse(
            '{"detail": "Detailed error information"}',
            ['http_code' => 400]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400: Detailed error information');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithInvalidJson():
    {
        $response = new MockResponse(
            'Plain text error message',
            ['http_code' => 500]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500: Plain text error message');
        HttpErrorHandler::handleHttpError($response);
    }

    public function testHandleErrorWithEmptyResponse():
    {
        $response = new MockResponse('', ['http_code' => 500]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500: HTTP 500 error');
        HttpErrorHandler::handleHttpError($response);
    }
}
