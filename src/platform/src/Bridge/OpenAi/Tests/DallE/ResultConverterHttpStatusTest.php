<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\DallE;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\ResultConverter;
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
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Incorrect API key provided');

        $converter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);
    }

    public function testThrowsAuthenticationExceptionWithFallbackMessage()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->with(false)->willReturn('');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $converter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponse()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->with(false)->willReturn(json_encode([
            'error' => [
                'message' => 'Your request was rejected as a result of our safety system.',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Your request was rejected as a result of our safety system.');

        $converter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);
    }

    public function testThrowsBadRequestExceptionWithFallbackMessage()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->with(false)->willReturn('');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $converter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);
    }
}