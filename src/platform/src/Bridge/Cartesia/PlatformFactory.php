<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Cartesia\Contract\CartesiaContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class PlatformFactory
{
    public static function create(
        string $apiKey,
        string $version,
        ?string $hostUrl = 'https://api.cartesia.ai',
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        return new Platform(
            [new CartesiaClient($httpClient, $apiKey, $version, $hostUrl)],
            [new CartesiaResultConverter()],
            $modelCatalog,
            $contract ?? CartesiaContract::create(),
            $eventDispatcher,
        );
    }
}
