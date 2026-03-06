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
use Symfony\AI\Store\Bridge\AzureSearch\SearchStore;
use Symfony\AI\Store\Bridge\AzureSearch\StoreFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreated()
    {
        $store = StoreFactory::create('foo', endpoint: 'https://test.search.windows.net/', apiKey: 'foo', apiVersion: '2023-11-01', httpClient: HttpClient::create());
        $this->assertInstanceOf(SearchStore::class, $store);
    }

    public function testStoreCanBeCreatedWithScopedHttpClient()
    {
        $store = StoreFactory::create('foo', httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://test.search.windows.net/', [
            'headers' => [
                'api-key' => 'foo',
            ],
            'query' => [
                'api-version' => '2023-11-01',
            ],
        ]));

        $this->assertInstanceOf(SearchStore::class, $store);
    }
}
