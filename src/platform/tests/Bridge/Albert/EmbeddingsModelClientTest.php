<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Albert;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Albert\EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class EmbeddingsModelClientTest extends TestCase
{
    public function testSupportsEmbeddingsModel()
    {
        $client = new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $embeddingsModel = new Embeddings('embedding-small');
        $this->assertTrue($client->supports($embeddingsModel));
    }

    public function testDoesNotSupportNonEmbeddingsModel()
    {
        $client = new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $gptModel = new Gpt('gpt-3.5-turbo');
        $this->assertFalse($client->supports($gptModel));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://albert.example.com/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"embedding-small","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsModelClient($httpClient, 'api-key', 'https://albert.example.com/v1');
        $modelClient->request(new Embeddings('embedding-small'), 'test text', []);
    }

    public function testItIsExecutingTheCorrectRequestWithCustomOptions()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://albert.example.com/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"dimensions":256,"model":"embedding-small","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsModelClient($httpClient, 'api-key', 'https://albert.example.com/v1');
        $modelClient->request(new Embeddings('embedding-small'), 'test text', ['dimensions' => 256]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://albert.example.com/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"embedding-small","input":["text1","text2","text3"]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new EmbeddingsModelClient($httpClient, 'api-key', 'https://albert.example.com/v1');
        $modelClient->request(new Embeddings('embedding-small'), ['text1', 'text2', 'text3'], []);
    }

    public function testRequestHandlesBaseUrlWithoutTrailingSlash()
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse(['data' => []]);
        });

        $client = new EmbeddingsModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Embeddings('embedding-small');
        $client->request($model, ['input' => 'test']);

        $this->assertSame('https://albert.example.com/v1/embeddings', $capturedUrl);
    }

    public function testRequestHandlesBaseUrlWithTrailingSlash()
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse(['data' => []]);
        });

        $client = new EmbeddingsModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Embeddings('embedding-small');
        $client->request($model, ['input' => 'test']);

        $this->assertSame('https://albert.example.com/v1/embeddings', $capturedUrl);
    }
}
