<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ClickHouse\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\ClickHouse\Store;
use Symfony\AI\Store\Bridge\ClickHouse\StoreFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithDsn()
    {
        $store = StoreFactory::create('symfony', 'embedding', 'http://symfony:symfony@127.0.0.1:8123');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $store = StoreFactory::create(httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'http://127.0.0.1:8123/'));

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreUsesDsnWithCredentials()
    {
        $requestedUrl = null;
        $requestedQuery = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$requestedQuery): JsonMockResponse {
            $requestedUrl = $url;
            $requestedQuery = $options['query'];

            return new JsonMockResponse(['data' => [['cnt' => '3']]]);
        });

        $store = StoreFactory::create('my_db', 'my_table', 'http://symfony:secret@127.0.0.1:8123', $httpClient);

        $this->assertSame(3, $store->count());
        $this->assertStringStartsWith('http://symfony:secret@127.0.0.1:8123/?', $requestedUrl);
        $this->assertSame('my_db', $requestedQuery['database']);
        $this->assertSame('SELECT count() AS cnt FROM my_table', $requestedQuery['query']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreNormalizesTrailingSlashOnDsn()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse(['data' => [['cnt' => '0']]]);
        });

        $store = StoreFactory::create(dsn: 'http://127.0.0.1:8123/', httpClient: $httpClient);
        $store->count();

        $this->assertStringStartsWith('http://127.0.0.1:8123/?', $requestedUrl);
        $this->assertStringContainsString('database=default', $requestedUrl);
    }

    public function testStoreKeepsPathPrefixOfDsn()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse(['data' => [['cnt' => '0']]]);
        });

        $store = StoreFactory::create(dsn: 'https://example.com/clickhouse', httpClient: $httpClient);
        $store->count();

        $this->assertStringStartsWith('https://example.com/clickhouse/?', $requestedUrl);
    }

    public function testStoreUsesPreScopedHttpClientWithoutDsn()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse(['data' => [['cnt' => '0']]]);
        });

        $store = StoreFactory::create(httpClient: ScopingHttpClient::forBaseUri($httpClient, 'https://example.com/clickhouse/'));
        $store->count();

        $this->assertStringStartsWith('https://example.com/clickhouse/?', $requestedUrl);
    }
}
