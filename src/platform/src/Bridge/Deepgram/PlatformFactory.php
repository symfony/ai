<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\Bridge\Deepgram\Contract\DeepgramContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PlatformFactory
{
    public static function create(
        ?string $httpEndpoint = 'https://api.deepgram.com/v1/',
        ?string $websocketEndpoint = 'https://api.deepgram.com/v1/',
        #[\SensitiveParameter] ?string $apiKey = null,
        bool $useWebsockets = false,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): PlatformInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient
            ? $httpClient
            : new EventSourceHttpClient($httpClient);

        if (null !== $apiKey) {
            $httpClient = ScopingHttpClient::forBaseUri($httpClient, $httpEndpoint, [
                'headers' => [
                    'Authorization' => \sprintf('Token %s', $apiKey),
                ],
            ]);
        }

        return new Platform(
            [$useWebsockets ? new WebsocketClient($websocketEndpoint, $apiKey) : new DeepgramClient($httpClient)],
            [new ResultConverter()],
            new ModelCatalog($httpClient),
            $contract ?? DeepgramContract::create(),
            $eventDispatcher,
        );
    }
}
