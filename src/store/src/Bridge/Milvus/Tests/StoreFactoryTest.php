<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Milvus\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Milvus\Store;
use Symfony\AI\Store\Bridge\Milvus\StoreFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithEndpoint()
    {
        $store = StoreFactory::create('my_database', 'my_collection', 'http://127.0.0.1:19530');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $store = StoreFactory::create('my_database', 'my_collection', httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'http://127.0.0.1:19530/'));

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreNormalizesTrailingSlashOnEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse(['code' => 0, 'data' => []]);
        });

        $store = StoreFactory::create('my_database', 'my_collection', 'http://127.0.0.1:19530/', httpClient: $httpClient);
        $store->drop();

        $this->assertSame('http://127.0.0.1:19530/v2/vectordb/databases/drop', $requestedUrl);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreKeepsPathPrefixOfEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse(['code' => 0, 'data' => []]);
        });

        $store = StoreFactory::create('my_database', 'my_collection', 'https://example.com/milvus', httpClient: $httpClient);
        $store->drop();

        $this->assertSame('https://example.com/milvus/v2/vectordb/databases/drop', $requestedUrl);
    }

    public function testStoreSendsApiKeyAsBearerToken()
    {
        $authorization = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$authorization): JsonMockResponse {
            $authorization = $options['normalized_headers']['authorization'][0] ?? null;

            return new JsonMockResponse(['code' => 0, 'data' => []]);
        });

        $store = StoreFactory::create('my_database', 'my_collection', 'http://127.0.0.1:19530', 'my-api-key', $httpClient);
        $store->drop();

        $this->assertSame('Authorization: Bearer my-api-key', $authorization);
    }

    public function testStoreSendsNoAuthorizationWithoutApiKey()
    {
        $authorization = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$authorization): JsonMockResponse {
            $authorization = $options['normalized_headers']['authorization'][0] ?? null;

            return new JsonMockResponse(['code' => 0, 'data' => []]);
        });

        $store = StoreFactory::create('my_database', 'my_collection', 'http://127.0.0.1:19530', httpClient: $httpClient);
        $store->drop();

        $this->assertNull($authorization);
    }

    public function testApiKeyWithoutEndpointThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Milvus "apiKey" requires an "endpoint"');

        StoreFactory::create('my_database', 'my_collection', apiKey: 'test-api-key', httpClient: new MockHttpClient());
    }
}
