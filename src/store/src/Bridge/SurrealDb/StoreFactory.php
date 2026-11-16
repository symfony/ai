<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\SurrealDb;

use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class StoreFactory
{
    public static function create(
        string $namespace,
        string $database,
        string $user,
        #[\SensitiveParameter] string $password,
        ?string $endpoint = null,
        ?HttpClientInterface $httpClient = null,
        string $table = 'vectors',
        string $vectorFieldName = '_vectors',
        string $metric = 'cosine',
        int $dimensions = 1536,
        bool $isNamespacedUser = false,
    ): StoreInterface&ManagedStoreInterface {
        $httpClient ??= HttpClient::create();

        if (null !== $endpoint) {
            $endpoint = rtrim($endpoint, '/');

            $httpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint);
        }

        return new Store($httpClient, $user, $password, $namespace, $database, $table, $vectorFieldName, $metric, $dimensions, $isNamespacedUser);
    }
}
