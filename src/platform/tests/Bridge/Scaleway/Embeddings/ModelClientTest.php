<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Scaleway\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Scaleway\Embeddings;
use Symfony\AI\Platform\Bridge\Scaleway\Embeddings\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ModelClientTest extends TestCase
{
    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new ModelClient(new MockHttpClient(), '');
    }

    public function testItAcceptsValidApiKey()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'scaleway-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'scaleway-api-key');

        $this->assertTrue($modelClient->supports(new Embeddings('bge-multilingual-gemma2')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"bge-multilingual-gemma2","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Embeddings('bge-multilingual-gemma2'), 'test text', []);
    }

    public function testItIsExecutingTheCorrectRequestWithCustomOptions()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"dimensions":256,"model":"bge-multilingual-gemma2","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Embeddings('bge-multilingual-gemma2'), 'test text', ['dimensions' => 256]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"bge-multilingual-gemma2","input":["text1","text2","text3"]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Embeddings('bge-multilingual-gemma2'), ['text1', 'text2', 'text3'], []);
    }
}
