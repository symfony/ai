<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterHttpStatusTest extends TestCase
{
    public function testThrowsAuthenticationExceptionOnInvalidApiKey()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->with(false)->willReturn(json_encode([
            'error' => [
                'code' => 401,
                'message' => 'API key not valid. Please pass a valid API key.',
                'status' => 'UNAUTHENTICATED',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('API key not valid. Please pass a valid API key.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsAuthenticationExceptionWithFallbackMessage()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->with(false)->willReturn('');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponse()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->with(false)->willReturn(json_encode([
            'error' => [
                'code' => 400,
                'message' => 'Invalid request',
                'status' => 'INVALID_ARGUMENT',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionWithFallbackMessage()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->with(false)->willReturn('');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $converter->convert(new RawHttpResult($httpResponse));
    }
}