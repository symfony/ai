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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\BaseResult;
use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;
use Symfony\AI\Platform\Result\Metadata\Metadata;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

#[CoversClass(ResultPromise::class)]
#[UsesClass(Metadata::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(RawResultAlreadySetException::class)]
#[Small]
final class ResultPromiseTest extends TestCase
{
    #[Test]
    public function itUnwrapsTheResultWhenGettingContent(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);
        $textResult = new TextResult('test content');

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects(self::once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willReturn($textResult);

        $resultPromise = new ResultPromise($resultConverter->convert(...), $rawHttpResult);

        self::assertSame('test content', $resultPromise->getResult()->getContent());
    }

    #[Test]
    public function itConvertsTheResponseOnlyOnce(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResult = new RawHttpResult($httpResponse);
        $textResult = new TextResult('test content');

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects(self::once())
            ->method('convert')
            ->with($rawHttpResult, [])
            ->willReturn($textResult);

        $resultPromise = new ResultPromise($resultConverter->convert(...), $rawHttpResult);

        // Call unwrap multiple times, but the converter should only be called once
        $resultPromise->await();
        $resultPromise->await();
        $resultPromise->getResult();
    }

    #[Test]
    public function itGetsRawResponseDirectly(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $resultConverter = $this->createStub(ResultConverterInterface::class);

        $resultPromise = new ResultPromise($resultConverter->convert(...), new RawHttpResult($httpResponse));

        self::assertSame($httpResponse, $resultPromise->getRawResult()->getObject());
    }

    #[Test]
    public function itSetsRawResponseOnUnwrappedResponseWhenNeeded(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResponse = $this->createResult(null);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('convert')->willReturn($unwrappedResponse);

        $resultPromise = new ResultPromise($resultConverter->convert(...), new RawHttpResult($httpResponse));
        $resultPromise->await();

        // The raw response in the model response is now set and not null anymore
        self::assertSame($httpResponse, $unwrappedResponse->getRawResult()->getObject());
    }

    #[Test]
    public function itDoesNotSetRawResponseOnUnwrappedResponseWhenAlreadySet(): void
    {
        $originHttpResponse = $this->createStub(SymfonyHttpResponse::class);
        $anotherHttpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResult = $this->createResult($anotherHttpResponse);

        $resultConverter = $this->createStub(ResultConverterInterface::class);
        $resultConverter->method('convert')->willReturn($unwrappedResult);

        $resultPromise = new ResultPromise($resultConverter->convert(...), new RawHttpResult($originHttpResponse));
        $resultPromise->await();

        // It is still the same raw response as set initially and so not overwritten
        self::assertSame($anotherHttpResponse, $unwrappedResult->getRawResult()->getObject());
    }

    #[Test]
    public function itPassesOptionsToConverter(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $rawHttpResponse = new RawHttpResult($httpResponse);
        $options = ['option1' => 'value1', 'option2' => 'value2'];

        $resultConverter = self::createMock(ResultConverterInterface::class);
        $resultConverter->expects(self::once())
            ->method('convert')
            ->with($rawHttpResponse, $options)
            ->willReturn($this->createResult(null));

        $resultPromise = new ResultPromise($resultConverter->convert(...), $rawHttpResponse, $options);
        $resultPromise->await();
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
