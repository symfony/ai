<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @deprecated since 0.8, use ProviderFactory instead
 */
final class PlatformFactory
{
    public const REGION_EU = ProviderFactory::REGION_EU;
    public const REGION_US = ProviderFactory::REGION_US;

    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?string $region = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $provider = ProviderFactory::create($apiKey, $httpClient, $modelCatalog, $contract, $region, $eventDispatcher);

        return Platform::create($provider);
    }
}
