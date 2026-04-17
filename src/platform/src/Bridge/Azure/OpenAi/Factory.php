<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\OpenAi;

use Symfony\AI\Platform\Bridge\Azure\Responses\ModelClient as ResponsesModelClient;
use Symfony\AI\Platform\Bridge\Generic\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as ResponsesResultConverter;
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
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        string $baseUrl,
        string $deployment,
        string $apiVersion,
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'azure-openai',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [
                new ResponsesModelClient($httpClient, $baseUrl, $apiKey, $deployment),
                new EmbeddingsModelClient($httpClient, $baseUrl, $deployment, $apiVersion, $apiKey),
                new WhisperModelClient($httpClient, $baseUrl, $deployment, $apiVersion, $apiKey),
            ],
            [new ResponsesResultConverter(), new Embeddings\ResultConverter(), new Whisper\ResultConverter()],
            $modelCatalog,
            $contract ?? OpenAiContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        string $baseUrl,
        string $deployment,
        string $apiVersion,
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'azure-openai',
    ): Platform {
        return new Platform([
            self::createProvider($baseUrl, $deployment, $apiVersion, $apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name),
        ], eventDispatcher: $eventDispatcher);
    }
}
