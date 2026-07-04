<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Batch\BatchManager;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\ModelClient as BatchModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Factory
{
    public const REGION_EU = 'EU';
    public const REGION_US = 'US';

    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?string $region = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'openai',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [
                new Gpt\ModelClient($httpClient, $apiKey, $region),
                new Embeddings\ModelClient($httpClient, $apiKey, $region),
                new Image\ModelClient($httpClient, $apiKey, $region),
                new TextToSpeech\ModelClient($httpClient, $apiKey, $region),
                new Whisper\ModelClient($httpClient, $apiKey, $region),
            ],
            [
                new Gpt\ResultConverter(),
                new Embeddings\ResultConverter(),
                new Image\ResultConverter(),
                new TextToSpeech\ResultConverter(),
                new Whisper\ResultConverter(),
                new Batch\ResultConverter(),
            ],
            $modelCatalog,
            $contract ?? OpenAiContract::create(),
            $eventDispatcher,
            [
                new Batch\SubmitModelClient($httpClient, $apiKey, $region),
            ],
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
        ?string $region = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'openai',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $region, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    public static function createBatchManager(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?string $region = null,
    ): BatchManager {
        return new BatchManager(new BatchModelClient($httpClient ?? HttpClient::create(), $apiKey, $region));
    }
}
