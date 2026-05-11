<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Generic;
use Symfony\AI\Platform\Bridge\OpenRouter\Rerank\ModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\Rerank\ResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author rglozman
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        Region $region = Region::WORLD,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'openrouter',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $baseUrl = match ($region) {
            Region::EU => 'https://eu.openrouter.ai/api',
            default => 'https://openrouter.ai/api',
        };

        $modelClients = [
            new Generic\Completions\ModelClient($httpClient, $baseUrl, $apiKey, '/v1/chat/completions'),
            new Generic\Embeddings\ModelClient($httpClient, $baseUrl, $apiKey, '/v1/embeddings'),
            new ModelClient($httpClient, $apiKey),
        ];
        $resultConverters = [
            new Generic\Completions\ResultConverter(),
            new Generic\Embeddings\ResultConverter(),
            new ResultConverter(),
        ];

        return new Provider($name, $modelClients, $resultConverters, $modelCatalog, $contract, $eventDispatcher);
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        Region $region = Region::WORLD,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'openrouter',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $region, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
