<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Bridge\ElevenLabs\Contract\ElevenLabsContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        string $endpoint = 'https://api.elevenlabs.io/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'elevenlabs',
        ?ModelCatalogInterface $modelCatalog = null,
    ): ProviderInterface {
        $httpClient = self::createHttpClient($endpoint, $apiKey, $httpClient);

        return new Provider(
            $name,
            [new ElevenLabsClient($httpClient)],
            [new ElevenLabsResultConverter($httpClient)],
            $modelCatalog ?? new ModelCatalog($httpClient),
            $contract ?? ElevenLabsContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        string $endpoint = 'https://api.elevenlabs.io/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'elevenlabs',
        ?ModelRouterInterface $modelRouter = null,
        ?ModelCatalogInterface $modelCatalog = null,
    ): Platform {
        return new Platform(
            [self::createProvider($endpoint, $apiKey, $httpClient, $contract, $eventDispatcher, $name, $modelCatalog)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    public static function createModelCatalog(
        string $endpoint = 'https://api.elevenlabs.io/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
    ): ModelCatalogInterface {
        return new ModelCatalog(self::createHttpClient($endpoint, $apiKey, $httpClient));
    }

    private static function createHttpClient(
        string $endpoint,
        #[\SensitiveParameter] ?string $apiKey,
        ?HttpClientInterface $httpClient,
    ): HttpClientInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        if (null === $apiKey) {
            return $httpClient;
        }

        // a trailing slash is required for the relative paths to resolve under the "/v1" prefix
        return ScopingHttpClient::forBaseUri($httpClient, rtrim($endpoint, '/').'/', [
            'headers' => [
                'xi-api-key' => $apiKey,
            ],
        ]);
    }
}
