<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi;

use Symfony\AI\Platform\Bridge\EdenAi\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\Contract\DocumentNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\Contract\DocumentUrlNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\Contract\ImageNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\Contract\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\Generic;
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
 * @author Mathieu Santostefano <msantostefano@proton.me>
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
        string $name = 'edenai',
        string $baseUrl = 'https://api.edenai.run',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $jobClient = self::createJobClient($apiKey, $httpClient, $baseUrl, $name);

        $modelClients = [
            new Generic\Completions\ModelClient($httpClient, $baseUrl, $apiKey, '/v3/chat/completions'),
            new Generic\Embeddings\ModelClient($httpClient, $baseUrl, $apiKey, '/v3/embeddings'),
            new UniversalAi\ModelClient($httpClient, $baseUrl, $apiKey),
            new SpeechToText\ModelClient($httpClient, $baseUrl, $apiKey),
        ];
        $resultConverters = [
            new Generic\Completions\ResultConverter(),
            new Generic\Embeddings\ResultConverter(),
            new Ocr\ResultConverter(),
            new DocumentParser\ResultConverter(),
            new Tts\ResultConverter($httpClient),
            new SpeechToText\ResultConverter($jobClient),
            new ImageAnalysis\ResultConverter(),
            new ImageGeneration\ResultConverter(),
        ];

        return new Provider(
            $name,
            $modelClients,
            $resultConverters,
            $modelCatalog,
            $contract ?? Contract::create([
                new AudioNormalizer(),
                new DocumentNormalizer(),
                new DocumentUrlNormalizer(),
                new ImageNormalizer(),
                new ImageUrlNormalizer(),
            ]),
            $eventDispatcher,
        );
    }

    /**
     * The client resolving the jobs this bridge hands out - typically in a worker picking up a
     * stored handle, without a provider or platform at hand.
     *
     * @param string $name the provider name stated on the handles this client creates
     */
    public static function createJobClient(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        string $baseUrl = 'https://api.edenai.run',
        string $name = 'edenai',
    ): EdenAiJobClient {
        return new EdenAiJobClient($httpClient ?? new EventSourceHttpClient(), $apiKey, $baseUrl, $name);
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
        string $name = 'edenai',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://api.edenai.run',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
