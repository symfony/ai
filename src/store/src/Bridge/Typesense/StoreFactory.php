<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Typesense;

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
        string $collection,
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        string $vectorFieldName = '_vectors',
        int $dimensions = 1536,
    ): StoreInterface&ManagedStoreInterface {
        $httpClient ??= HttpClient::create();

        if (null !== $endpoint) {
            $defaultOptions = [];
            if (null !== $apiKey) {
                $defaultOptions['headers'] = [
                    'X-TYPESENSE-API-KEY' => $apiKey,
                ];
            }

            $endpoint = rtrim($endpoint, '/').'/';

            $httpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint, $defaultOptions);
        }

        return new Store($httpClient, $collection, $vectorFieldName, $dimensions);
    }
}
