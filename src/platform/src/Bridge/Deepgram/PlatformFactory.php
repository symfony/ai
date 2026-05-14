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
use Symfony\AI\Platform\Bridge\Deepgram\Websocket\WebsocketConnectorInterface;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PlatformFactory
{
    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        string $httpEndpoint = 'https://api.deepgram.com/v1/',
        string $websocketEndpoint = 'wss://api.deepgram.com/v1',
        bool $useWebsockets = false,
        ?HttpClientInterface $httpClient = null,
        ?WebsocketConnectorInterface $websocketConnector = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'deepgram',
    ): PlatformInterface {
        return new Platform(
            [self::createProvider(
                $apiKey,
                $httpEndpoint,
                $websocketEndpoint,
                $useWebsockets,
                $httpClient,
                $websocketConnector,
                $contract,
                $eventDispatcher,
                $name,
            )],
            eventDispatcher: $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        string $httpEndpoint = 'https://api.deepgram.com/v1/',
        string $websocketEndpoint = 'wss://api.deepgram.com/v1',
        bool $useWebsockets = false,
        ?HttpClientInterface $httpClient = null,
        ?WebsocketConnectorInterface $websocketConnector = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'deepgram',
    ): ProviderInterface {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The Deepgram API key cannot be empty.');
        }

        $scopedHttpClient = ScopingHttpClient::forBaseUri($httpClient ?? HttpClient::create(), $httpEndpoint, [
            'headers' => [
                'Authorization' => \sprintf('Token %s', $apiKey),
            ],
        ]);

        $modelClient = $useWebsockets
            ? new WebsocketClient($websocketEndpoint, $apiKey, $websocketConnector)
            : new DeepgramClient($scopedHttpClient);

        return new Provider(
            $name,
            [$modelClient],
            [new ResultConverter($scopedHttpClient)],
            new ModelCatalog($scopedHttpClient),
            $contract ?? DeepgramContract::create(),
            $eventDispatcher,
        );
    }
}
