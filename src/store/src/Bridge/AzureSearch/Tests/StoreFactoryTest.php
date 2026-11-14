<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\AzureSearch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\AzureSearch\SearchStore;
use Symfony\AI\Store\Bridge\AzureSearch\StoreFactory;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Uid\Uuid;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreated()
    {
        $store = StoreFactory::create('foo', 'vector', 'https://test.search.windows.net/', 'foo', '2023-11-01', HttpClient::create());
        $this->assertInstanceOf(SearchStore::class, $store);
    }

    public function testStoreCanBeCreatedWithScopedHttpClient()
    {
        $store = StoreFactory::create('foo', 'vector', null, null, null, ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://test.search.windows.net/', [
            'headers' => [
                'api-key' => 'foo',
            ],
            'query' => [
                'api-version' => '2023-11-01',
            ],
        ]));

        $this->assertInstanceOf(SearchStore::class, $store);
    }

    public function testStoreKeepsPathPrefixWithoutTrailingSlash()
    {
        $resolvedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$resolvedUrl): JsonMockResponse {
            $resolvedUrl = $url;

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        $store = StoreFactory::create('foo', 'vector', 'https://test.search.windows.net/proxy', null, null, $httpClient);
        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])));

        $this->assertSame('https://test.search.windows.net/proxy/indexes/foo/docs/index', $resolvedUrl);
    }
}
