<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

final class DeferredResultTest extends TestCase
{
    public function testItUnwrapsTheResultWhenGettingContent()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);
        $textResult = new TextResult('test content');

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willReturn($textResult);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);

        $this->assertSame('test content', $deferredResult->getResult()->getContent());
    }

    public function testItConvertsTheResponseOnlyOnce()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);
        $textResult = new TextResult('test content');

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willReturn($textResult);

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResult);

        // Call unwrap multiple times, but the converter should only be called once
        $deferredResult->getResult();
        $deferredResult->getResult();
        $deferredResult->getResult();
    }

    public function testItGetsRawResponseDirectly()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $resultConverter = $this->createStub(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, new RawHttpResult($httpResponse));

        $this->assertSame($httpResponse, $deferredResult->getRawResult()->getObject());
    }

    public function testItSetsRawResponseOnUnwrappedResponseWhenNeeded()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResponse = $this->createResult(null);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('convert')->willReturn($unwrappedResponse);

        $deferredResult = new DeferredResult($resultConverter, new RawHttpResult($httpResponse));
        $deferredResult->getResult();

        // The raw response in the model response is now set and not null anymore
        $this->assertSame($httpResponse, $unwrappedResponse->getRawResult()->getObject());
    }

    public function testItDoesNotSetRawResponseOnUnwrappedResponseWhenAlreadySet()
    {
        $originHttpResponse = $this->createStub(SymfonyHttpResponse::class);
        $anotherHttpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResult = $this->createResult($anotherHttpResponse);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('convert')->willReturn($unwrappedResult);

        $deferredResult = new DeferredResult($resultConverter, new RawHttpResult($originHttpResponse));
        $deferredResult->getResult();

        // It is still the same raw response as set initially and so not overwritten
        $this->assertSame($anotherHttpResponse, $unwrappedResult->getRawResult()->getObject());
    }

    public function testItPassesOptionsToConverter()
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResponse = new RawHttpResult($httpResponse);
        $options = ['option1' => 'value1', 'option2' => 'value2'];

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with($rawHttpResponse, $options)
            ->willReturn($this->createResult(null));

        $deferredResult = new DeferredResult($resultConverter, $rawHttpResponse, $options);
        $deferredResult->getResult();
    }

    /**
     * Workaround for low deps because mocking the ResponseInterface leads to an exception with
     * mock creation "Type Traversable|object|array|string|null contains both object and a class type"
     * in PHPUnit MockClass.
     */
    private function createResult(?SymfonyHttpResponse $httpResponse): ResultInterface
    {
        $rawResult = null !== $httpResponse ? new RawHttpResult($httpResponse) : null;

        return new class($rawResult) extends BaseResult {
            public function __construct(protected ?RawResultInterface $rawResult)
            {
            }

            public function getContent(): string
            {
                return 'test content';
            }
        };
    }
}
