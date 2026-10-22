<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Bridge\Ollama\Contract\OllamaContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'ollama',
        ?ModelCatalogInterface $modelCatalog = null,
    ): ProviderInterface {
        $httpClient = self::createHttpClient($endpoint, $apiKey, $httpClient);

        return new Provider(
            $name,
            [new DecisionModelClient($httpClient), new OllamaClient($httpClient)],
            [new DecisionResultConverter(), new OllamaResultConverter()],
            $modelCatalog ?? new ModelCatalog($httpClient),
            $contract ?? OllamaContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'ollama',
        ?ModelRouterInterface $modelRouter = null,
        ?ModelCatalogInterface $modelCatalog = null,
    ): Platform {
        return new Platform(
            [self::createProvider($endpoint, $apiKey, $httpClient, $contract, $eventDispatcher, $name, $modelCatalog)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    public static function createModelCatalog(
        ?string $endpoint = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
    ): ModelCatalogInterface {
        return new ModelCatalog(self::createHttpClient($endpoint, $apiKey, $httpClient));
    }

    private static function createHttpClient(
        ?string $endpoint,
        #[\SensitiveParameter] ?string $apiKey,
        ?HttpClientInterface $httpClient,
    ): HttpClientInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        if (null === $endpoint) {
            return $httpClient;
        }

        $defaultOptions = [];
        if (null !== $apiKey) {
            $defaultOptions['auth_bearer'] = $apiKey;
        }

        return ScopingHttpClient::forBaseUri($httpClient, $endpoint, $defaultOptions);
    }
}
