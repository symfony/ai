<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Generic\Completions\ModelClient;
use Symfony\AI\Platform\Bridge\Generic\Embeddings;
use Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PlatformFactory
{
    public static function create(
        string $baseUrl,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new FallbackModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new ModelClient($httpClient, $baseUrl, $apiKey),
                new Embeddings\ModelClient($httpClient, $baseUrl, $apiKey),
            ],
            [
                new CompletionsResultConverter(),
                new Embeddings\ResultConverter(),
            ],
            $modelCatalog,
            $contract,
            $eventDispatcher,
        );
    }
}
