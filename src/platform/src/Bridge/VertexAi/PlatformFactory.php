<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

use Symfony\AI\Platform\Bridge\VertexAi\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ModelClient as GeminiModelClient;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ResultConverter as GeminiResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final readonly class PlatformFactory
{
    public static function create(
        string $location,
        string $projectId,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new GeminiModelClient($httpClient, $location, $projectId)],
            [new GeminiResultConverter()],
            $contract ?? GeminiContract::create(),
        );
    }
}
