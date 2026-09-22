<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Bridge\Venice\Contract\VeniceContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
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
        string $endpoint = 'https://api.venice.ai/api/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'venice',
    ): ProviderInterface {
        $httpClient = self::createScopedHttpClient($endpoint, $apiKey, $httpClient);

        return new Provider(
            $name,
            [new VeniceClient($httpClient)],
            [new VeniceResultConverter($name)],
            new ModelCatalog($httpClient),
            $contract ?? VeniceContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * The client resolving the generations this bridge queues, e.g. in a worker holding a stored handle.
     */
    public static function createJobClient(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $endpoint = 'https://api.venice.ai/api/v1/',
        ?HttpClientInterface $httpClient = null,
    ): VeniceJobClient {
        return new VeniceJobClient(self::createScopedHttpClient($endpoint, $apiKey, $httpClient));
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $endpoint = 'https://api.venice.ai/api/v1/',
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'venice',
        ?ModelRouterInterface $modelRouter = null,
    ): PlatformInterface {
        return new Platform(
            [self::createProvider($endpoint, $apiKey, $httpClient, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    private static function createScopedHttpClient(
        string $endpoint,
        #[\SensitiveParameter] ?string $apiKey,
        ?HttpClientInterface $httpClient,
    ): HttpClientInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $httpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint);

        if (null === $apiKey) {
            return $httpClient;
        }

        return ScopingHttpClient::forBaseUri($httpClient, $endpoint, [
            'auth_bearer' => $apiKey,
        ]);
    }
}
