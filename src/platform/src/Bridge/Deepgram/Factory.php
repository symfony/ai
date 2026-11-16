<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\Bridge\Deepgram\Contract\DeepgramContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
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
        string $endpoint = 'https://api.deepgram.com/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'deepgram',
        ?ModelCatalogInterface $modelCatalog = null,
    ): ProviderInterface {
        $httpClient = self::createHttpClient($endpoint, $apiKey, $httpClient);

        return new Provider(
            $name,
            [new DeepgramClient($httpClient)],
            [new DeepgramResultConverter($httpClient)],
            $modelCatalog ?? new ModelCatalog($httpClient),
            $contract ?? DeepgramContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        string $endpoint = 'https://api.deepgram.com/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'deepgram',
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
        string $endpoint = 'https://api.deepgram.com/v1/',
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
        $httpClient ??= HttpClient::create();

        if (null === $apiKey) {
            return $httpClient;
        }

        // a trailing slash is required for the relative paths ("models", "speak", "listen") to resolve under it
        return ScopingHttpClient::forBaseUri($httpClient, rtrim($endpoint, '/').'/', [
            'headers' => [
                'Authorization' => \sprintf('Token %s', $apiKey),
            ],
        ]);
    }
}
