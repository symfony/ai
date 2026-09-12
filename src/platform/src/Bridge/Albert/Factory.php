<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert;

use Symfony\AI\Platform\Bridge\Albert\Completions\ResultConverter;
use Symfony\AI\Platform\Bridge\Generic\Completions as GenericCompletions;
use Symfony\AI\Platform\Bridge\Generic\Embeddings as GenericEmbeddings;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
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
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'albert',
    ): ProviderInterface {
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('The Albert URL must start with "https://".');
        }
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        // The completions result converter is Albert's own, to expose the carbon footprint it
        // reports alongside the token usage; everything else follows the OpenAI-compatible schema.
        return new Provider(
            $name,
            [
                new GenericCompletions\ModelClient($httpClient, $baseUrl, $apiKey),
                new GenericEmbeddings\ModelClient($httpClient, $baseUrl, $apiKey),
            ],
            [
                new ResultConverter(),
                new GenericEmbeddings\ResultConverter(),
            ],
            $modelCatalog,
            eventDispatcher: $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'albert',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $baseUrl, $httpClient, $modelCatalog, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
