<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield;

use Symfony\AI\Platform\Bridge\Higgsfield\Contract\HiggsfieldContract;
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
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Factory
{
    public const DEFAULT_BASE_URL = 'https://platform.higgsfield.ai';

    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        #[\SensitiveParameter] string $apiSecret,
        ?string $baseUrl = null,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalogInterface $modelCatalog = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'higgsfield',
    ): ProviderInterface {
        $httpClient = self::createScopedHttpClient($apiKey, $apiSecret, $baseUrl, $httpClient);

        return new Provider(
            $name,
            [new HiggsfieldClient($httpClient)],
            [new HiggsfieldResultConverter($name)],
            $modelCatalog ?? new CuratedModelCatalog(new ModelCatalog($httpClient)),
            $contract ?? HiggsfieldContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * The client resolving the generations this bridge hands out, e.g. in a worker holding a stored handle.
     */
    public static function createJobClient(
        #[\SensitiveParameter] string $apiKey,
        #[\SensitiveParameter] string $apiSecret,
        ?string $baseUrl = null,
        ?HttpClientInterface $httpClient = null,
    ): HiggsfieldJobClient {
        return new HiggsfieldJobClient(self::createScopedHttpClient($apiKey, $apiSecret, $baseUrl, $httpClient));
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        #[\SensitiveParameter] string $apiSecret,
        ?string $baseUrl = null,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalogInterface $modelCatalog = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'higgsfield',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $apiSecret, $baseUrl, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    private static function createScopedHttpClient(
        #[\SensitiveParameter] string $apiKey,
        #[\SensitiveParameter] string $apiSecret,
        ?string $baseUrl,
        ?HttpClientInterface $httpClient,
    ): HttpClientInterface {
        return ScopingHttpClient::forBaseUri($httpClient ?? HttpClient::create(), $baseUrl ?? self::DEFAULT_BASE_URL, [
            'headers' => [
                'Authorization' => \sprintf('Key %s:%s', $apiKey, $apiSecret),
            ],
        ]);
    }
}
