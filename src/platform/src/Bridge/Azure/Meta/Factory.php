<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Meta;

use Symfony\AI\Platform\Bridge\Meta\ModelCatalog;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Factory
{
    public static function createProvider(
        string $baseUrl,
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'azure-meta',
    ): ProviderInterface {
        $modelClient = new LlamaModelClient($httpClient ?? HttpClient::create(), $baseUrl, $apiKey);

        return new Provider($name, [$modelClient], [new LlamaResultConverter()], $modelCatalog, $contract, $eventDispatcher);
    }

    public static function createPlatform(
        string $baseUrl,
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'azure-meta',
    ): Platform {
        return new Platform([
            self::createProvider($baseUrl, $apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name),
        ]);
    }
}
