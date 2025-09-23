<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Cerebras;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cerebras\Model;
use Symfony\AI\Platform\Bridge\Cerebras\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\NotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServiceUnavailableException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
#[CoversClass(ResultConverter::class)]
#[UsesClass(Model::class)]
#[UsesClass(TextResult::class)]
#[Small]
class ResultConverterTest extends TestCase
{
    public function testSupportsCorrectModel()
    {
        $converter = new ResultConverter();
        $model = new Model(Model::GPT_OSS_120B);

        $this->assertTrue($converter->supports($model));
    }

    public function testConvertSuccessfulTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Hello from Cerebras!',
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello from Cerebras!', $result->getContent());
    }

    public function testThrowsAuthenticationException()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')
            ->with(false)
            ->willReturn('{"error": {"message": "Invalid API key"}}');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsNotFoundException()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(404);
        $httpResponse->method('getContent')
            ->with(false)
            ->willReturn('{"error": {"message": "Model not found"}}');

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Model not found');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsServiceUnavailableException()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(503);
        $httpResponse->method('getContent')
            ->with(false)
            ->willReturn('{"error": {"message": "Service temporarily unavailable"}}');

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Service temporarily unavailable');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsRuntimeExceptionWhenNoContent()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsCerebrasApiError()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([
            'type' => 'api_error',
            'message' => 'Something went wrong with the API',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cerebras API error: "Something went wrong with the API"');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsGenericRuntimeExceptionForMissingChoices()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output.');

        $converter->convert(new RawHttpResult($httpResponse));
    }
}
