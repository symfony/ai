<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Typesense\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Typesense\Store;
use Symfony\AI\Store\Bridge\Typesense\StoreFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithHttpClientAndRequiredInfos()
    {
        $store = StoreFactory::create('foo', 'http://127.0.0.1:8108', 'bar');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $store = StoreFactory::create('foo', null, null, ScopingHttpClient::forBaseUri(HttpClient::create(), 'http://127.0.0.1:8108', [
            'headers' => [
                'X-TYPESENSE-API-KEY' => 'bar',
            ],
        ]));

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreKeepsPathPrefixWithoutTrailingSlash()
    {
        $resolvedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$resolvedUrl): JsonMockResponse {
            $resolvedUrl = $url;

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        $store = StoreFactory::create('foo', 'http://localhost:8108/proxy', null, $httpClient);
        $store->drop();

        $this->assertSame('http://localhost:8108/proxy/collections/foo', $resolvedUrl);
    }
}
