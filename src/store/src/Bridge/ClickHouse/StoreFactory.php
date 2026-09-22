<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ClickHouse;

use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StoreFactory
{
    /**
     * @param string|null $dsn ClickHouse HTTP interface URL, credentials can be passed as user info, e.g. "http://user:password@localhost:8123"
     */
    public static function create(
        string $databaseName = 'default',
        string $tableName = 'embedding',
        ?string $dsn = null,
        ?HttpClientInterface $httpClient = null,
    ): StoreInterface&ManagedStoreInterface {
        $httpClient ??= HttpClient::create();

        if (null !== $dsn) {
            $httpClient = ScopingHttpClient::forBaseUri($httpClient, rtrim($dsn, '/').'/');
        }

        return new Store($httpClient, $databaseName, $tableName);
    }
}
