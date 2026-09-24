<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ManticoreSearch;

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
    public static function create(
        string $table,
        ?string $endpoint = null,
        ?HttpClientInterface $httpClient = null,
        string $field = '_vectors',
        string $type = 'hnsw',
        string $similarity = 'cosine',
        int $dimensions = 1536,
        string $quantization = '8bit',
    ): StoreInterface&ManagedStoreInterface {
        $httpClient ??= HttpClient::create();

        if (null !== $endpoint) {
            $httpClient = ScopingHttpClient::forBaseUri($httpClient, rtrim($endpoint, '/').'/');
        }

        return new Store($httpClient, $table, $field, $type, $similarity, $dimensions, $quantization);
    }
}
