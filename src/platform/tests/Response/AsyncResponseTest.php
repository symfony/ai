<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Response\AsyncResponse;
use Symfony\AI\Platform\Response\BaseResponse;
use Symfony\AI\Platform\Response\Exception\RawResponseAlreadySetException;
use Symfony\AI\Platform\Response\Metadata\Metadata;
use Symfony\AI\Platform\Response\ResponseInterface;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\ResponseConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

#[CoversClass(AsyncResponse::class)]
#[UsesClass(Metadata::class)]
#[UsesClass(TextResponse::class)]
#[UsesClass(RawResponseAlreadySetException::class)]
#[Small]
final class AsyncResponseTest extends TestCase
{
    #[Test]
    public function itUnwrapsTheResponseWhenGettingContent(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $textResponse = new TextResponse('test content');

        $responseConverter = self::createMock(ResponseConverterInterface::class);
        $responseConverter->expects(self::once())
            ->method('convert')
            ->with($httpResponse, [])
            ->willReturn($textResponse);

        $asyncResponse = new AsyncResponse($responseConverter, $httpResponse);

        self::assertSame('test content', $asyncResponse->getContent());
    }

    #[Test]
    public function itConvertsTheResponseOnlyOnce(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $textResponse = new TextResponse('test content');

        $responseConverter = self::createMock(ResponseConverterInterface::class);
        $responseConverter->expects(self::once())
            ->method('convert')
            ->with($httpResponse, [])
            ->willReturn($textResponse);

        $asyncResponse = new AsyncResponse($responseConverter, $httpResponse);

        // Call unwrap multiple times, but the converter should only be called once
        $asyncResponse->unwrap();
        $asyncResponse->unwrap();
        $asyncResponse->getContent();
    }

    #[Test]
    public function itGetsRawResponseDirectly(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $responseConverter = $this->createStub(ResponseConverterInterface::class);

        $asyncResponse = new AsyncResponse($responseConverter, $httpResponse);

        self::assertSame($httpResponse, $asyncResponse->getRawResponse());
    }

    #[Test]
    public function itThrowsExceptionWhenSettingRawResponse(): void
    {
        self::expectException(RawResponseAlreadySetException::class);

        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $responseConverter = $this->createStub(ResponseConverterInterface::class);

        $asyncResponse = new AsyncResponse($responseConverter, $httpResponse);
        $asyncResponse->setRawResponse($httpResponse);
    }

    #[Test]
    public function itSetsRawResponseOnUnwrappedResponseWhenNeeded(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResponse = $this->createResponse(null);

        $responseConverter = $this->createStub(ResponseConverterInterface::class);
        $responseConverter->method('convert')->willReturn($unwrappedResponse);

        $asyncResponse = new AsyncResponse($responseConverter, $httpResponse);
        $asyncResponse->unwrap();

        // The raw response in the model response is now set and not null anymore
        self::assertSame($httpResponse, $unwrappedResponse->getRawResponse());
    }

    #[Test]
    public function itDoesNotSetRawResponseOnUnwrappedResponseWhenAlreadySet(): void
    {
        $originHttpResponse = $this->createStub(SymfonyHttpResponse::class);
        $anotherHttpResponse = $this->createStub(SymfonyHttpResponse::class);

        $unwrappedResponse = $this->createResponse($anotherHttpResponse);

        $responseConverter = $this->createStub(ResponseConverterInterface::class);
        $responseConverter->method('convert')->willReturn($unwrappedResponse);

        $asyncResponse = new AsyncResponse($responseConverter, $originHttpResponse);
        $asyncResponse->unwrap();

        // It is still the same raw response as set initially and so not overwritten
        self::assertSame($anotherHttpResponse, $unwrappedResponse->getRawResponse());
    }

    /**
     * Workaround for low deps because mocking the ResponseInterface leads to an exception with
     * mock creation "Type Traversable|object|array|string|null contains both object and a class type"
     * in PHPUnit MockClass.
     */
    private function createResponse(?SymfonyHttpResponse $rawResponse): ResponseInterface
    {
        return new class($rawResponse) extends BaseResponse {
            public function __construct(protected ?SymfonyHttpResponse $rawResponse)
            {
            }

            public function getContent(): string
            {
                return 'test content';
            }

            public function getRawResponse(): ?SymfonyHttpResponse
            {
                return $this->rawResponse;
            }
        };
    }

    #[Test]
    public function itPassesOptionsToConverter(): void
    {
        $httpResponse = $this->createStub(SymfonyHttpResponse::class);
        $options = ['option1' => 'value1', 'option2' => 'value2'];

        $responseConverter = self::createMock(ResponseConverterInterface::class);
        $responseConverter->expects(self::once())
            ->method('convert')
            ->with($httpResponse, $options)
            ->willReturn($this->createResponse(null));

        $asyncResponse = new AsyncResponse($responseConverter, $httpResponse, $options);
        $asyncResponse->unwrap();
    }
}
