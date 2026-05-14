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
use Symfony\AI\Platform\Bridge\Voyage\EmbeddingsClient;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsClientTest extends TestCase
{
    public function testItSendsExpectedRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.voyageai.com/v1/embeddings', $url);
            self::assertSame(json_encode([
                'model' => 'voyage-3.5',
                'input' => 'Hello, world!',
                'input_type' => null,
                'truncation' => true,
                'output_dimension' => 300,
                'encoding_format' => null,
            ]), $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new EmbeddingsClient($httpClient, 'test-key');
        $client->request(new Voyage('voyage-3.5'), 'Hello, world!', ['dimensions' => 300]);
    }

    public function testItSupportsModelsWithEmbeddingsEndpoint()
    {
        $client = new EmbeddingsClient(new MockHttpClient(), 'test-key');

        $textModel = new Voyage('voyage-3.5', [], [], [new Endpoint(EmbeddingsClient::ENDPOINT)]);
        $multimodalModel = new Voyage('voyage-multimodal-3', [], [], [new Endpoint('voyage.multimodal_embeddings')]);

        $this->assertTrue($client->supports($textModel));
        $this->assertFalse($client->supports($multimodalModel));
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                ['embedding' => [0.1, 0.2, 0.3]],
                ['embedding' => [0.4, 0.5, 0.6]],
            ],
        ]);

        $client = new EmbeddingsClient(new MockHttpClient(), 'test-key');
        $vectorResult = $client->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(2, $vectorResult->getContent());
        $this->assertSame([0.1, 0.2, 0.3], $vectorResult->getContent()[0]->getData());
        $this->assertSame([0.4, 0.5, 0.6], $vectorResult->getContent()[1]->getData());
    }

    public function testItThrowsWhenResponseDoesNotContainData()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $client = new EmbeddingsClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain embedding data.');

        $client->convert(new RawHttpResult($response));
    }

    public function testItExposesItsEndpointIdentifier()
    {
        $client = new EmbeddingsClient(new MockHttpClient(), 'test-key');

        $this->assertSame('voyage.embeddings', $client->endpoint());
        $this->assertSame(EmbeddingsClient::ENDPOINT, $client->endpoint());
    }

    public function testItRoundTripsThroughHttpClient()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [['embedding' => [0.1, 0.2]]],
        ]));
        $client = new EmbeddingsClient($httpClient, 'test-key');

        $raw = $client->request(new Voyage('voyage-3.5'), 'hello');
        $result = $client->convert($raw);

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertSame([0.1, 0.2], $result->getContent()[0]->getData());
    }
}
