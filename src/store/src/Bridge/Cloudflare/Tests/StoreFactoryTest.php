<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cloudflare\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Cloudflare\Store;
use Symfony\AI\Store\Bridge\Cloudflare\StoreFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreatedWithHttpClientAndRequiredInfos()
    {
        $store = StoreFactory::create('my-index', 'account-id', 'api-key');

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testStoreCanBeCreatedWithScopingHttpClient()
    {
        $store = StoreFactory::create('my-index', null, null, ScopingHttpClient::forBaseUri(HttpClient::create(), 'https://api.cloudflare.com/client/v4/accounts/account-id/', [
            'auth_bearer' => 'api-key',
        ]));

        $this->assertInstanceOf(Store::class, $store);
    }
}
