<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAI;

use Symfony\AI\Platform\Bridge\OpenAI\DallE\ModelClient as DallEModelClient;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings\ModelClient as EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings\ResponseConverter as EmbeddingsResponseConverter;
use Symfony\AI\Platform\Bridge\OpenAI\GPT\ModelClient as GPTModelClient;
use Symfony\AI\Platform\Bridge\OpenAI\GPT\ResponseConverter as GPTResponseConverter;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper\AudioNormalizer;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper\ModelClient as WhisperModelClient;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper\ResponseConverter as WhisperResponseConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter]
        string $apiKey,
        ?HttpClientInterface $httpClient = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $dallEModelClient = new DallEModelClient($httpClient, $apiKey);

        return new Platform(
            [
                new GPTModelClient($httpClient, $apiKey),
                new EmbeddingsModelClient($httpClient, $apiKey),
                $dallEModelClient,
                new WhisperModelClient($httpClient, $apiKey),
            ],
            [
                new GPTResponseConverter(),
                new EmbeddingsResponseConverter(),
                $dallEModelClient,
                new WhisperResponseConverter(),
            ],
            Contract::create(new AudioNormalizer()),
        );
    }
}
