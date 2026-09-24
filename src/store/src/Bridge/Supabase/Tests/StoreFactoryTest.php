<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Supabase\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Supabase\Store;
use Symfony\AI\Store\Bridge\Supabase\StoreFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithEndpointAndApiKey()
    {
        $store = StoreFactory::create('https://test.supabase.co', 'test-api-key');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreSendsApiKeyHeaders()
    {
        $requestedUrl = null;
        $requestedHeaders = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$requestedHeaders): MockResponse {
            $requestedUrl = $url;
            $requestedHeaders = $options['normalized_headers'];

            return new MockResponse('', ['http_code' => 200, 'response_headers' => ['Content-Range' => '0-0/3']]);
        });

        $store = StoreFactory::create('https://test.supabase.co', 'test-api-key', $httpClient);

        $this->assertSame(3, $store->count());
        $this->assertSame('https://test.supabase.co/rest/v1/documents?select=count', $requestedUrl);
        $this->assertSame(['apikey: test-api-key'], $requestedHeaders['apikey']);
        $this->assertSame(['Authorization: Bearer test-api-key'], $requestedHeaders['authorization']);
        $this->assertSame(['Prefer: count=exact'], $requestedHeaders['prefer']);
    }

    public function testStoreDoesNotSendApiKeyHeadersWithoutApiKey()
    {
        $requestedHeaders = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedHeaders): MockResponse {
            $requestedHeaders = $options['normalized_headers'];

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = StoreFactory::create('https://test.supabase.co', httpClient: $httpClient);
        $store->clear();

        $this->assertArrayNotHasKey('apikey', $requestedHeaders);
        $this->assertArrayNotHasKey('authorization', $requestedHeaders);
    }

    public function testStoreNormalizesTrailingSlashOnEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = StoreFactory::create('https://test.supabase.co/', 'test-api-key', $httpClient, table: 'movies');
        $store->clear();

        $this->assertSame('https://test.supabase.co/rest/v1/movies?id=not.is.null', $requestedUrl);
    }

    public function testStoreKeepsPathPrefixOfEndpoint()
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = StoreFactory::create('https://example.com/prefix', 'test-api-key', $httpClient);
        $store->clear();

        $this->assertSame('https://example.com/prefix/rest/v1/documents?id=not.is.null', $requestedUrl);
    }

    public function testStoreCanBeCreatedWithScopedHttpClient()
    {
        $requestedUrl = null;
        $requestedHeaders = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$requestedHeaders): MockResponse {
            $requestedUrl = $url;
            $requestedHeaders = $options['normalized_headers'];

            return new MockResponse('', ['http_code' => 200]);
        });

        $store = StoreFactory::create(httpClient: ScopingHttpClient::forBaseUri($httpClient, 'https://test.supabase.co/', [
            'auth_bearer' => 'test-api-key',
            'headers' => ['apikey' => 'test-api-key'],
        ]));
        $store->clear();

        $this->assertSame('https://test.supabase.co/rest/v1/documents?id=not.is.null', $requestedUrl);
        $this->assertSame(['apikey: test-api-key'], $requestedHeaders['apikey']);
        $this->assertSame(['Authorization: Bearer test-api-key'], $requestedHeaders['authorization']);
    }

    public function testApiKeyWithoutEndpointThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Supabase "apiKey" requires an "endpoint"');

        StoreFactory::create(apiKey: 'test-api-key', httpClient: new MockHttpClient());
    }
}
