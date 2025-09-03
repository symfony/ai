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
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ModelClient as EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ResultConverter as EmbeddingsResultConverter;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient as GeminiModelClient;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter as GeminiResultConverter;
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
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new EmbeddingsModelClient($httpClient, $apiKey), new GeminiModelClient($httpClient, $apiKey)],
            [new EmbeddingsResultConverter(), new GeminiResultConverter()],
            $contract ?? GeminiContract::create(),
        );
    }
}
