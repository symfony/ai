<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LMStudio;

use Symfony\AI\Platform\Bridge\LMStudio\Embeddings\ModelClient;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author André Lubian <lubiana123@gmail.com>
 */
class PlatformFactory
{
    public static function create(
        string $hostUrl = 'http://localhost:1234',
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new ModelClient($httpClient, $hostUrl),
                new Completions\ModelClient($httpClient, $hostUrl),
            ],
            [
                new Embeddings\ResultConverter(),
                new Completions\ResultConverter(),
            ], $contract);
    }
}
