<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert;

use Symfony\AI\Platform\Bridge\Albert\Validator\AlbertValidator;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl,
        ?HttpClientInterface $httpClient = null,
    ): Platform {
        AlbertValidator::validateUrl($baseUrl);
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new GptModelClient($httpClient, $apiKey, $baseUrl),
                new EmbeddingsModelClient($httpClient, $apiKey, $baseUrl),
            ],
            [new Gpt\ResultConverter(), new Embeddings\ResultConverter()],
            Contract::create(),
        );
    }
}
