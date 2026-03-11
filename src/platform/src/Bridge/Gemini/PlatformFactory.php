<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Contract\GeminiContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Roy Garrido
 */
final class PlatformFactory
{
    public static function create(
        string $endpoint = 'https://generativelanguage.googleapis.com',
        string $version = 'v1beta',
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $defaultOptions = [];
        if (null !== $apiKey) {
            $defaultOptions['headers']['x-goog-api-key'] = $apiKey;
        }

        $httpClient = ScopingHttpClient::forBaseUri($httpClient, \sprintf('%s/%s/', $endpoint, $version), $defaultOptions);

        return new Platform(
            [new ModelClient($httpClient)],
            [new ResultConverter()],
            new GeminiApiCatalog($httpClient),
            $contract ?? GeminiContract::create(),
            $eventDispatcher,
        );
    }
}
