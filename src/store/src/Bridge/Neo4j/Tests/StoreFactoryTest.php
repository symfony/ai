<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Neo4j\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Neo4j\Store;
use Symfony\AI\Store\Bridge\Neo4j\StoreFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithEndpoint()
    {
        $store = StoreFactory::create('neo4j', 'vector_index', 'Document', 'http://127.0.0.1:7474', 'neo4j', 'password');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $store = StoreFactory::create('neo4j', 'vector_index', 'Document', httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'http://127.0.0.1:7474/'));

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreNormalizesTrailingSlashOnEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse([]);
        });

        $store = StoreFactory::create('neo4j', 'vector_index', 'Document', 'http://127.0.0.1:7474/', httpClient: $httpClient);
        $store->setup();

        $this->assertSame('http://127.0.0.1:7474/db/neo4j/query/v2', $requestedUrl);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreKeepsPathPrefixOfEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse([]);
        });

        $store = StoreFactory::create('neo4j', 'vector_index', 'Document', 'https://example.com/neo4j', httpClient: $httpClient);
        $store->setup();

        $this->assertSame('https://example.com/neo4j/db/neo4j/query/v2', $requestedUrl);
    }

    public function testStoreSendsBasicAuthCredentials()
    {
        $requestHeaders = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestHeaders): JsonMockResponse {
            $requestHeaders = $options['normalized_headers'];

            return new JsonMockResponse([]);
        });

        $store = StoreFactory::create('neo4j', 'vector_index', 'Document', 'http://127.0.0.1:7474', 'neo4j', 'password', $httpClient);
        $store->setup();

        $this->assertSame(['Authorization: Basic '.base64_encode('neo4j:password')], $requestHeaders['authorization']);
    }

    public function testStoreSendsNoAuthWithoutUsername()
    {
        $requestHeaders = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestHeaders): JsonMockResponse {
            $requestHeaders = $options['normalized_headers'];

            return new JsonMockResponse([]);
        });

        $store = StoreFactory::create('neo4j', 'vector_index', 'Document', 'http://127.0.0.1:7474', httpClient: $httpClient);
        $store->setup();

        $this->assertArrayNotHasKey('authorization', $requestHeaders);
    }

    public function testCredentialsWithoutEndpointThrow()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Neo4j "username" and "password" require an "endpoint"');

        StoreFactory::create('neo4j', 'vector_index', 'Document', username: 'neo4j', password: 'password', httpClient: new MockHttpClient());
    }

    public function testPasswordWithoutUsernameThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Neo4j "password" requires a "username".');

        StoreFactory::create('neo4j', 'vector_index', 'Document', 'http://127.0.0.1:7474', password: 'password');
    }
}
