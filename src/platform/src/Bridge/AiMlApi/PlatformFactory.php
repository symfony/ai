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
use Symfony\AI\Platform\Bridge\AiMlApi\Embeddings\ModelClient;
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
        string $hostUrl = 'https://api.aimlapi.com',
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        return new Platform(
            [
                new ModelClient($apiKey, $httpClient, $hostUrl),
                new Completions\ModelClient($apiKey, $httpClient, $hostUrl),
            ],
            [
                new Embeddings\ResultConverter(),
                new Completions\ResultConverter(),
            ],
            new ModelCatalog(),
            $contract,
            $eventDispatcher,
        );
    }
}
