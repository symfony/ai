<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Batch\JobClient;
use Symfony\AI\Platform\Bridge\Gemini\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ModelClient as EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ResultConverter as EmbeddingsResultConverter;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient as GeminiModelClient;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter as GeminiResultConverter;
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
 * @author Roy Garrido
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
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'gemini',
        string $baseUrl = 'https://generativelanguage.googleapis.com',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [new EmbeddingsModelClient($httpClient, $apiKey, $baseUrl), new GeminiModelClient($httpClient, $apiKey, $baseUrl)],
            [new EmbeddingsResultConverter(), new GeminiResultConverter($name)],
            $modelCatalog,
            $contract ?? GeminiContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'gemini',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://generativelanguage.googleapis.com',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    /**
     * The client resolving the batches this bridge hands out - typically in a worker picking up a
     * stored handle, without a provider or platform at hand.
     */
    public static function createJobClient(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $baseUrl = 'https://generativelanguage.googleapis.com',
    ): JobClient {
        return new JobClient($httpClient ?? new EventSourceHttpClient(), $apiKey, $baseUrl);
    }
}
