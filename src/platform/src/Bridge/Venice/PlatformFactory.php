<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Bridge\Venice\Contract\Contract as VeniceContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
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
        #[\SensitiveParameter] ?string $apiKey = null,
        string $endpoint = 'https://api.venice.ai/api/v1/',
        ?HttpClientInterface $httpClient = null,
        ClockInterface $clock = new MonotonicClock(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): PlatformInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $scopedHttpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint);

        if (null !== $apiKey) {
            $scopedHttpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint, [
                'auth_bearer' => $apiKey,
            ]);
        }

        return new Platform(
            [new VeniceClient($scopedHttpClient, $clock)],
            [new ResultConverter()],
            new ModelCatalog($httpClient),
            $contract ?? VeniceContract::create(),
            $eventDispatcher,
        );
    }
}
