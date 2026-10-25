<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Scaleway\Embeddings\ResultConverter;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ResultConverterTest extends TestCase
{
    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn(json_decode($this->getEmbeddingStub(), true));

        $vectorResult = (new ResultConverter())->convert(new RawHttpResult($result));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testThrowsServerExceptionOnServerError()
    {
        $response = (new MockHttpClient(new MockResponse('<html>Bad Gateway</html>', ['http_code' => 502])))->request('POST', 'https://api.scaleway.ai/v1/embeddings');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 502).');

        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    private function getEmbeddingStub(): string
    {
        return <<<'JSON'
            {
              "object": "list",
              "data": [
                {
                  "object": "embedding",
                  "index": 0,
                  "embedding": [0.3, 0.4, 0.4]
                },
                {
                  "object": "embedding",
                  "index": 1,
                  "embedding": [0.0, 0.0, 0.2]
                }
              ]
            }
            JSON;
    }
}
