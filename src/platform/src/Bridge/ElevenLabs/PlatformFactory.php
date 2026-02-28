<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\ElevenLabs\Contract\ElevenLabsContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PlatformFactory
{
    public static function create(
        string $endpoint = 'https://api.elevenlabs.io/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        bool $apiCatalog = false,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        if (null !== $apiKey) {
            $httpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint, [
                'headers' => [
                    'xi-api-key' => $apiKey,
                ],
            ]);
        }

        return new Platform(
            [new ElevenLabsClient($httpClient)],
            [new ElevenLabsResultConverter($httpClient)],
            $apiCatalog ? new ElevenLabsApiCatalog($httpClient) : $modelCatalog,
            $contract ?? ElevenLabsContract::create(),
            $eventDispatcher,
        );
    }
}
