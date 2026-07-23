<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral;

use Symfony\AI\Platform\Bridge\Mistral\Contract\DocumentNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Contract\DocumentUrlNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Contract\ImageUrlNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Contract\ToolNormalizer;
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
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'mistral',
        string $baseUrl = 'https://api.mistral.ai',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [new Embeddings\ModelClient($httpClient, $apiKey, $baseUrl), new Llm\ModelClient($httpClient, $apiKey, $baseUrl), new Ocr\ModelClient($httpClient, $apiKey), new SpeechToText\ModelClient($httpClient, $apiKey, $baseUrl)],
            [new Embeddings\ResultConverter(), new Llm\ResultConverter(), new Ocr\ResultConverter(), new SpeechToText\ResultConverter()],
            $modelCatalog,
            $contract ?? Contract::create([
                new ToolNormalizer(),
                new DocumentNormalizer(),
                new DocumentUrlNormalizer(),
                new ImageUrlNormalizer(),
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
        string $name = 'mistral',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://api.mistral.ai',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
