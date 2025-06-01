<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Google;

use Symfony\AI\Platform\Bridge\Google\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Google\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\Google\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Roy Garrido
 */
final readonly class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter]
        string $apiKey,
        ?HttpClientInterface $httpClient = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $responseHandler = new ModelHandler($httpClient, $apiKey);

        return new Platform([$responseHandler], [$responseHandler], Contract::create(
            new AssistantMessageNormalizer(),
            new MessageBagNormalizer(),
            new UserMessageNormalizer(),
        ));
    }
}
