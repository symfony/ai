<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class PlatformFactory
{
    public static function create(
        string $hostUrl = 'http://localhost:12434',
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new Completions\ModelClient($httpClient, $hostUrl),
                new Embeddings\ModelClient($httpClient, $hostUrl),
            ],
            [
                new Embeddings\ResultConverter(),
                new Completions\ResultConverter(),
            ], $contract);
    }
}
