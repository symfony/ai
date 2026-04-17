<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        string $hostUrl = 'http://localhost:12434',
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'dockermodelrunner',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [
                new Completions\ModelClient($httpClient, $hostUrl),
                new Embeddings\ModelClient($httpClient, $hostUrl),
            ],
            [
                new Embeddings\ResultConverter(),
                new Completions\ResultConverter(),
            ],
            $modelCatalog,
            $contract,
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        string $hostUrl = 'http://localhost:12434',
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'dockermodelrunner',
    ): Platform {
        return new Platform([
            self::createProvider($hostUrl, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name),
        ]);
    }
}
