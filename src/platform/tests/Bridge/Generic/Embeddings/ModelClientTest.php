<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Generic\Embeddings;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\Embeddings\ModelClient;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'http://localhost:8000');

        $this->assertTrue($modelClient->supports(new EmbeddingsModel('text-embedding-3-small')));
    }

    public function testItIsNotSupportingTheIncorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'http://localhost:8000');

        $this->assertFalse($modelClient->supports(new CompletionsModel('gpt-4o-mini')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"text-embedding-3-small","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'http://localhost:8000', 'sk-api-key');
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test text');
    }

    public function testItIsExecutingTheCorrectRequestWithCustomOptions()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"dimensions":256,"model":"text-embedding-3-large","input":"test text"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'http://localhost:8000', 'sk-api-key');
        $modelClient->request(new EmbeddingsModel('text-embedding-3-large'), 'test text', ['dimensions' => 256]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:8000/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"model":"text-embedding-3-small","input":["text1","text2","text3"]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'http://localhost:8000', 'sk-api-key');
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), ['text1', 'text2', 'text3']);
    }

    #[TestWith(['https://api.inference.eu', 'https://api.inference.eu/v1/embeddings'])]
    #[TestWith(['https://api.inference.com', 'https://api.inference.com/v1/embeddings'])]
    public function testItUsesCorrectBaseUrl(string $baseUrl, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, $baseUrl, 'sk-api-key');
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input');
    }

    #[TestWith(['/custom/path', 'https://api.inference.com/custom/path'])]
    #[TestWith(['/v1/alternative/endpoint', 'https://api.inference.com/v1/alternative/endpoint'])]
    public function testsItUsesCorrectPathIfProvided(string $path, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'https://api.inference.com', 'sk-api-key', $path);
        $modelClient->request(new EmbeddingsModel('text-embedding-3-small'), 'test input');
    }
}
