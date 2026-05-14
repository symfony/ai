<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\MultimodalEmbeddingsClient;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MultimodalEmbeddingsClientTest extends TestCase
{
    public function testItSendsExpectedRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.voyageai.com/v1/multimodalembeddings', $url);
            self::assertSame(json_encode([
                'model' => 'voyage-multimodal-3',
                'inputs' => 'Hello, world!',
                'input_type' => null,
                'truncation' => true,
                'output_encoding' => null,
            ]), $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new MultimodalEmbeddingsClient($httpClient, 'test-key');
        $client->request(new Voyage('voyage-multimodal-3'), 'Hello, world!');
    }

    public function testItSupportsMultimodalModelsOnly()
    {
        $client = new MultimodalEmbeddingsClient(new MockHttpClient(), 'test-key');

        $multimodalModel = new Voyage('voyage-multimodal-3', [], [], [new Endpoint(MultimodalEmbeddingsClient::ENDPOINT)]);
        $textModel = new Voyage('voyage-3.5', [], [], [new Endpoint('voyage.embeddings')]);

        $this->assertTrue($client->supports($multimodalModel));
        $this->assertFalse($client->supports($textModel));
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [['embedding' => [0.1, 0.2, 0.3]]],
        ]);

        $client = new MultimodalEmbeddingsClient(new MockHttpClient(), 'test-key');
        $vectorResult = $client->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
    }

    public function testItThrowsWhenResponseDoesNotContainData()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $client = new MultimodalEmbeddingsClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embedding data.');

        $client->convert(new RawHttpResult($response));
    }

    public function testItExposesItsEndpointIdentifier()
    {
        $client = new MultimodalEmbeddingsClient(new MockHttpClient(), 'test-key');

        $this->assertSame('voyage.multimodal_embeddings', $client->endpoint());
        $this->assertSame(MultimodalEmbeddingsClient::ENDPOINT, $client->endpoint());
    }
}
