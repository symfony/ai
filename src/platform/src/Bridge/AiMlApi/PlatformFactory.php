<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AiMlApi;

use Symfony\AI\Platform\Bridge\AiMlApi\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Generic\PlatformFactory as GenericPlatformFactory;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de
 */
class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        string $baseUrl = 'https://api.aimlapi.com',
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        return GenericPlatformFactory::create(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            httpClient: $httpClient,
            modelCatalog: new ModelCatalog(),
            contract: $contract ?? Contract::create(new AssistantMessageNormalizer()),
            eventDispatcher: $eventDispatcher,
        );
    }
}
