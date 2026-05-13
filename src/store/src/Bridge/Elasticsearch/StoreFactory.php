<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Elasticsearch;

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
        string $indexName,
        ?string $endpoint = null,
        ?HttpClientInterface $httpClient = null,
        string $vectorsField = '_vectors',
        int $dimensions = 1536,
        string $similarity = 'cosine',
    ): StoreInterface&ManagedStoreInterface {
        $httpClient ??= HttpClient::create();

        if (null !== $endpoint) {
            $httpClient = ScopingHttpClient::forBaseUri($httpClient, rtrim($endpoint, '/').'/');
        }

        return new Store($httpClient, $indexName, $vectorsField, $dimensions, $similarity);
    }
}
