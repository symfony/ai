<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\HelixDb;

use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class StoreFactory
{
    public static function create(
        string $endpointUrl = 'http://127.0.0.1:6969',
        ?HttpClientInterface $httpClient = null,
        int $embeddingsDimension = 1536,
        int $defaultTopK = 5,
    ): StoreInterface&ManagedStoreInterface {
        return new Store($httpClient ?? HttpClient::create(), $endpointUrl, $embeddingsDimension, $defaultTopK);
    }
}
