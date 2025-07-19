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

use Symfony\AI\Platform\Bridge\OpenAI\Whisper\AudioNormalizer;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper\ModelClient as WhisperModelClient;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper\ResultConverter as WhisperResponseConverter;
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
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new GPT\ModelClient($httpClient, $apiKey),
                new Embeddings\ModelClient($httpClient, $apiKey),
                new DallE\ModelClient($httpClient, $apiKey),
                new WhisperModelClient($httpClient, $apiKey),
            ],
            [
                new GPT\ResultConverter(),
                new Embeddings\ResultConverter(),
                new DallE\ResultConverter(),
                new WhisperResponseConverter(),
            ],
            $contract ?? Contract::create(new AudioNormalizer()),
        );
    }
}
