<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Qdrant;

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
        string $collectionName,
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        int $embeddingsDimension = 1536,
        string $embeddingsDistance = 'Cosine',
        bool $async = false,
    ): StoreInterface&ManagedStoreInterface {
        if (null !== $endpoint) {
            $defaultOptions = [];
            if (null !== $apiKey) {
                $defaultOptions['headers'] = [
                    'api-key' => $apiKey,
                ];
            }

            $httpClient = ScopingHttpClient::forBaseUri($httpClient ?? HttpClient::create(), $endpoint, $defaultOptions);
        }

        return new Store($httpClient, $collectionName, $embeddingsDimension, $embeddingsDistance, $async);
    }
}
