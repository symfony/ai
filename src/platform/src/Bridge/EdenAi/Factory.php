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
use Symfony\Component\Clock\ClockInterface;
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
        ?ClockInterface $clock = null,
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $modelClients = [
            new Generic\Completions\ModelClient($httpClient, $baseUrl, $apiKey, '/v3/chat/completions'),
            new Generic\Embeddings\ModelClient($httpClient, $baseUrl, $apiKey, '/v3/embeddings'),
            new UniversalAi\ModelClient($httpClient, $baseUrl, $apiKey),
            new SpeechToText\ModelClient($httpClient, $baseUrl, $apiKey, $clock),
        ];
        $resultConverters = [
            new Generic\Completions\ResultConverter(),
            new Generic\Embeddings\ResultConverter(),
            new Ocr\ResultConverter(),
            new DocumentParser\ResultConverter(),
            new Tts\ResultConverter($httpClient),
            new SpeechToText\ResultConverter(),
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
        ?ClockInterface $clock = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name, $baseUrl, $clock)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
