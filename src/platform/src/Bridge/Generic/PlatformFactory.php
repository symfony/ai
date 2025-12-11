<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class PlatformFactory
{
    public static function create(
        string $baseUrl,
        ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new FallbackModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        bool $supportsCompletions = true,
        bool $supportsEmbeddings = true,
        string $completionsPath = '/v1/chat/completions',
        string $embeddingsPath = '/v1/embeddings',
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $modelClients = [];
        $resultConverters = [];
        if ($supportsCompletions) {
            $modelClients[] = new Completions\ModelClient($httpClient, $baseUrl, $apiKey, $completionsPath);
            $resultConverters[] = new Completions\ResultConverter();
        }
        if ($supportsEmbeddings) {
            $modelClients[] = new Embeddings\ModelClient($httpClient, $baseUrl, $apiKey, $embeddingsPath);
            $resultConverters[] = new Embeddings\ResultConverter();
        }

        return new Platform($modelClients, $resultConverters, $modelCatalog, $contract, $eventDispatcher);
    }
}
