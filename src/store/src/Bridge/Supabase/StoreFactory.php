<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Supabase;

use Symfony\AI\Store\Exception\InvalidArgumentException;
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
     * @param string|null $apiKey Sent both as "apikey" header and as bearer token
     */
    public static function create(
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        string $table = 'documents',
        string $vectorFieldName = 'embedding',
        int $vectorDimension = 1536,
        string $functionName = 'match_documents',
    ): StoreInterface {
        if (null === $endpoint && null !== $apiKey) {
            throw new InvalidArgumentException('The Supabase "apiKey" requires an "endpoint", configure it on the HTTP client otherwise.');
        }

        $httpClient ??= HttpClient::create();

        if (null !== $endpoint) {
            $defaultOptions = [];
            if (null !== $apiKey) {
                $defaultOptions['auth_bearer'] = $apiKey;
                $defaultOptions['headers'] = [
                    'apikey' => $apiKey,
                ];
            }

            $httpClient = ScopingHttpClient::forBaseUri($httpClient, rtrim($endpoint, '/').'/', $defaultOptions);
        }

        return new Store($httpClient, $table, $vectorFieldName, $vectorDimension, $functionName);
    }
}
