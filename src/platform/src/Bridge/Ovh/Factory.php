<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh;

use Symfony\AI\Platform\Bridge\Generic\Completions;
use Symfony\AI\Platform\Bridge\Generic\Embeddings;
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
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class Factory
{
    private const BASE_URL = 'https://oai.endpoints.kepler.ai.cloud.ovh.net';

    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'ovh',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $modelClients = [
            new Completions\ModelClient($httpClient, self::BASE_URL, $apiKey),
            new Embeddings\ModelClient($httpClient, self::BASE_URL, $apiKey),
            new SpeechToText\ModelClient($httpClient, $apiKey, self::BASE_URL),
            new TextToSpeech\ModelClient($httpClient, $apiKey),
            new Image\ModelClient($httpClient, $apiKey, self::BASE_URL),
            new Decision\ModelClient($httpClient, $apiKey, self::BASE_URL),
        ];
        $resultConverters = [
            new Completions\ResultConverter(),
            new Embeddings\ResultConverter(),
            new SpeechToText\ResultConverter(),
            new TextToSpeech\ResultConverter(),
            new Image\ResultConverter(),
            new Decision\ResultConverter(),
        ];

        return new Provider(
            $name,
            $modelClients,
            $resultConverters,
            $modelCatalog,
            $contract ?? Contract::create([
                new SpeechToText\AudioNormalizer(),
            ]),
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
        string $name = 'ovh',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
