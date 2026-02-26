<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\AzureSearch;

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
        string $vectorField = 'vector',
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?string $apiVersion = null,
        ?HttpClientInterface $httpClient = null,
    ): StoreInterface {
        if (null !== $endpoint) {
            $defaultOptions = [];
            if (null !== $apiKey) {
                $defaultOptions['headers']['api-key'] = $apiKey;
            }

            if (null !== $apiVersion) {
                $defaultOptions['query']['api-version'] = $apiVersion;
            }

            $httpClient = ScopingHttpClient::forBaseUri($httpClient ?? HttpClient::create(), $endpoint, $defaultOptions);
        }

        return new SearchStore($httpClient, $indexName, $vectorField);
    }
}
