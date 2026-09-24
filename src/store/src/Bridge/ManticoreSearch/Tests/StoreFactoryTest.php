<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ManticoreSearch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\ManticoreSearch\Store;
use Symfony\AI\Store\Bridge\ManticoreSearch\StoreFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithEndpoint()
    {
        $store = StoreFactory::create('bar', 'http://127.0.0.1:9308');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $store = StoreFactory::create('bar', httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'http://127.0.0.1:9308/'));

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreNormalizesTrailingSlashOnEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse('Query OK, 0 rows affected (0.006 sec)'.\PHP_EOL, [
                'http_code' => 200,
            ]);
        });

        $store = StoreFactory::create('bar', 'http://127.0.0.1:9308/', $httpClient);
        $store->setup();

        $this->assertSame('http://127.0.0.1:9308/cli', $requestedUrl);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreKeepsPathPrefixOfEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse('Query OK, 0 rows affected (0.006 sec)'.\PHP_EOL, [
                'http_code' => 200,
            ]);
        });

        $store = StoreFactory::create('bar', 'https://example.com/manticore', $httpClient);
        $store->setup();

        $this->assertSame('https://example.com/manticore/cli', $requestedUrl);
    }

    public function testStoreUsesPreScopedHttpClientWithoutEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse('Query OK, 0 rows affected (0.006 sec)'.\PHP_EOL, [
                'http_code' => 200,
            ]);
        });

        $store = StoreFactory::create('bar', httpClient: ScopingHttpClient::forBaseUri($httpClient, 'http://manticore:9308/'));
        $store->clear();

        $this->assertSame('http://manticore:9308/cli', $requestedUrl);
    }
}
