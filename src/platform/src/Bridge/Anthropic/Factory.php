<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\Bridge\Anthropic\Batch\JobClient;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AnthropicContract;
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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Factory
{
    /**
     * @param 'none'|'short'|'long' $cacheRetention
     * @param non-empty-string      $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $cacheRetention = 'short',
        string $name = 'anthropic',
        string $baseUrl = 'https://api.anthropic.com',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [new ModelClient($httpClient, $apiKey, $cacheRetention, $baseUrl)],
            [new ResultConverter($name)],
            $modelCatalog,
            $contract ?? AnthropicContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * The client resolving the batches this bridge hands out - typically in a worker picking up a
     * stored handle, without a provider or platform at hand.
     *
     * @param string $baseUrl Base URL of an Anthropic-compatible endpoint, without trailing slash
     */
    public static function createJobClient(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $baseUrl = 'https://api.anthropic.com',
    ): JobClient {
        return new JobClient($httpClient ?? new EventSourceHttpClient(), $apiKey, $baseUrl);
    }

    /**
     * @param 'none'|'short'|'long' $cacheRetention
     * @param non-empty-string      $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $cacheRetention = 'short',
        string $name = 'anthropic',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://api.anthropic.com',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $cacheRetention, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
