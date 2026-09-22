<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Milvus;

use Symfony\AI\Store\Exception\InvalidArgumentException;
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
        string $database,
        string $collection,
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        string $vectorFieldName = '_vectors',
        int $dimensions = 1536,
        string $metricType = 'COSINE',
    ): StoreInterface&ManagedStoreInterface {
        if (null === $endpoint && null !== $apiKey) {
            throw new InvalidArgumentException('The Milvus "apiKey" requires an "endpoint", configure it on the HTTP client otherwise.');
        }

        $httpClient ??= HttpClient::create();

        if (null !== $endpoint) {
            $defaultOptions = [];
            if (null !== $apiKey) {
                $defaultOptions['auth_bearer'] = $apiKey;
            }

            $httpClient = ScopingHttpClient::forBaseUri($httpClient, rtrim($endpoint, '/').'/', $defaultOptions);
        }

        return new Store($httpClient, $database, $collection, $vectorFieldName, $dimensions, $metricType);
    }
}
