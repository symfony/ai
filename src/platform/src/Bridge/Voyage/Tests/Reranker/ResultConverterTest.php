<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Tests\Reranker;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\Reranker;
use Symfony\AI\Platform\Bridge\Voyage\Reranker\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsRerankerModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Reranker('rerank-2.5')));
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('Internal Server Error');

        $converter = new ResultConverter();

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItReadsTheDetailOfAnErrorResponse()
    {
        $response = (new MockHttpClient(new MockResponse('{"detail":"Provided API key is invalid."}', ['http_code' => 401])))->request('POST', 'https://api.voyageai.com/v1/rerank');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Provided API key is invalid.');

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testItConvertsResponseToRerankingResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'object' => 'list',
            'data' => [
                ['index' => 0, 'relevance_score' => 0.95],
                ['index' => 1, 'relevance_score' => 0.42],
            ],
            'model' => 'rerank-2.5',
            'usage' => ['total_tokens' => 18],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(RerankingResult::class, $result);
        $entries = $result->getContent();
        $this->assertCount(2, $entries);
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertSame(0.95, $entries[0]->getScore());
        $this->assertSame(1, $entries[1]->getIndex());
        $this->assertSame(0.42, $entries[1]->getScore());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainResults()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain reranking results.');

        $converter->convert(new RawHttpResult($response));
    }
}
