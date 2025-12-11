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
use Symfony\AI\Platform\Bridge\Generic\Completions;
use Symfony\AI\Platform\Bridge\Generic\Embeddings;
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
                new Completions\ModelClient($httpClient, $baseUrl, $apiKey),
                new Embeddings\ModelClient($httpClient, $baseUrl, $apiKey),
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
