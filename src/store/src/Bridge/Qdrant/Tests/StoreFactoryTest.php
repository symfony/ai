<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Qdrant\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Qdrant\Store;
use Symfony\AI\Store\Bridge\Qdrant\StoreFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCannotBeCreatedWithoutScopingHttpClientAndRequiredInfos()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The HttpClient must be an instance of "%s" or both "endpoint" and "apiKey" must be provided.', ScopingHttpClient::class));
        $this->expectExceptionCode(0);
        StoreFactory::create('foo', httpClient: HttpClient::create());
    }

    public function testStoreCanBeCreatedWithHttpClientAndRequiredInfos()
    {
        $store = StoreFactory::create('foo', 'http://127.0.0.1:6333', 'bar');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $store = StoreFactory::create('foo', httpClient: ScopingHttpClient::forBaseUri(HttpClient::create(), 'http://127.0.0.1:6333', [
            'headers' => [
                'api-key' => 'bar',
            ],
        ]));

        $this->assertInstanceOf(Store::class, $store);
    }
}
