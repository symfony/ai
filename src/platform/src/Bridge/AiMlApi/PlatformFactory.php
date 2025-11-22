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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
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
        return new Platform(
            [
                new Completions\ModelClient($apiKey, $baseUrl, httpClient: $httpClient),
                new Embeddings\ModelClient($apiKey, $baseUrl, httpClient: $httpClient),
            ],
            [
                new Completions\ResultConverter(),
                new Embeddings\ResultConverter(),
            ],
            new ModelCatalog(),
            $contract,
            $eventDispatcher,
        );
    }
}
