<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        string $baseUrl,
        ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new FallbackModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        bool $supportsCompletions = true,
        bool $supportsEmbeddings = true,
        string $completionsPath = '/v1/chat/completions',
        string $embeddingsPath = '/v1/embeddings',
        string $name = 'generic',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $modelClients = [];
        $resultConverters = [];
        if ($supportsCompletions) {
            $modelClients[] = new Completions\ModelClient($httpClient, $baseUrl, $apiKey, $completionsPath);
            $resultConverters[] = new Completions\ResultConverter();
        }
        if ($supportsEmbeddings) {
            $modelClients[] = new Embeddings\ModelClient($httpClient, $baseUrl, $apiKey, $embeddingsPath);
            $resultConverters[] = new Embeddings\ResultConverter();
        }

        return new Provider($name, $modelClients, $resultConverters, $modelCatalog, $contract, $eventDispatcher);
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        string $baseUrl,
        ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new FallbackModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        bool $supportsCompletions = true,
        bool $supportsEmbeddings = true,
        string $completionsPath = '/v1/chat/completions',
        string $embeddingsPath = '/v1/embeddings',
        string $name = 'generic',
    ): Platform {
        return new Platform([
            self::createProvider($baseUrl, $apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $supportsCompletions, $supportsEmbeddings, $completionsPath, $embeddingsPath, $name),
        ]);
    }
}
