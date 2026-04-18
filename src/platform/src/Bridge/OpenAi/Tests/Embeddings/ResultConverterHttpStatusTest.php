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
                'message' => 'Incorrect API key provided',
                'type' => 'invalid_request_error',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Incorrect API key provided');

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
                'message' => 'Invalid input: expected non-empty array',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid input: expected non-empty array');

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