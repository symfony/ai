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

use Symfony\AI\Platform\Bridge\Gemini\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ModelClient as EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ResultConverter as EmbeddingsResultConverter;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient as GeminiModelClient;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter as GeminiResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
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
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [new EmbeddingsModelClient($httpClient, $apiKey), new GeminiModelClient($httpClient, $apiKey)],
            [new EmbeddingsResultConverter(), new GeminiResultConverter()],
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
    ): Platform {
        return new Platform([
            self::createProvider($apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name),
        ], eventDispatcher: $eventDispatcher);
    }
}
