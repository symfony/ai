<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\AudioNormalizer;
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
        string $baseUrl,
        string $deployment,
        string $apiVersion,
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $embeddingsModelClient = new EmbeddingsModelClient($httpClient, $baseUrl, $deployment, $apiVersion, $apiKey);
        $gptModelClient = new GptModelClient($httpClient, $baseUrl, $deployment, $apiVersion, $apiKey);
        $whisperModelClient = new WhisperModelClient($httpClient, $baseUrl, $deployment, $apiVersion, $apiKey);

        return new Platform(
            [$gptModelClient, $embeddingsModelClient, $whisperModelClient],
            [new Gpt\ResultConverter(), new Embeddings\ResultConverter(), new Whisper\ResultConverter()],
            $contract ?? Contract::create(new AudioNormalizer()),
        );
    }
}
