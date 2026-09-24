<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Neo4j;

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
        string $databaseName,
        string $vectorIndexName,
        string $nodeName,
        ?string $endpoint = null,
        ?string $username = null,
        #[\SensitiveParameter] ?string $password = null,
        ?HttpClientInterface $httpClient = null,
        string $embeddingsField = 'embeddings',
        int $embeddingsDimension = 1536,
        string $embeddingsDistance = 'cosine',
        bool $quantization = false,
    ): StoreInterface&ManagedStoreInterface {
        if (null === $username && null !== $password) {
            throw new InvalidArgumentException('The Neo4j "password" requires a "username".');
        }

        if (null === $endpoint && null !== $username) {
            throw new InvalidArgumentException('The Neo4j "username" and "password" require an "endpoint", configure them on the HTTP client otherwise.');
        }

        $httpClient ??= HttpClient::create();

        if (null !== $endpoint) {
            $defaultOptions = [];
            if (null !== $username) {
                $defaultOptions['auth_basic'] = [$username, $password ?? ''];
            }

            $endpoint = rtrim($endpoint, '/').'/';

            $httpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint, $defaultOptions);
        }

        return new Store($httpClient, $databaseName, $vectorIndexName, $nodeName, $embeddingsField, $embeddingsDimension, $embeddingsDistance, $quantization);
    }
}
