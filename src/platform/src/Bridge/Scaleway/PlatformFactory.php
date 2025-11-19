<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Scaleway\Embeddings\ModelClient as ScalewayEmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\Scaleway\Embeddings\ResultConverter as ScalewayEmbeddingsResponseConverter;
use Symfony\AI\Platform\Bridge\Scaleway\Llm\ModelClient as ScalewayModelClient;
use Symfony\AI\Platform\Bridge\Scaleway\Llm\ResultConverter as ScalewayResponseConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new ScalewayModelClient($httpClient, $apiKey),
                new ScalewayEmbeddingsModelClient($httpClient, $apiKey),
            ],
            [
                new ScalewayResponseConverter(),
                new ScalewayEmbeddingsResponseConverter(),
            ],
            $modelCatalog,
            $contract,
            $eventDispatcher,
        );
    }
}
