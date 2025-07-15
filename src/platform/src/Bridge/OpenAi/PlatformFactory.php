<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\ModelClient as WhisperModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\ResultConverter as WhisperResponseConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class PlatformFactory
{
    public const REGION_EU = 'EU';
    public const REGION_US = 'US';

    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?Contract $contract = null,
        ?string $region = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new Gpt\ModelClient($httpClient, $apiKey, $region),
                new Embeddings\ModelClient($httpClient, $apiKey, $region),
                new DallE\ModelClient($httpClient, $apiKey, $region),
                new WhisperModelClient($httpClient, $apiKey, $region),
            ],
            [
                new DallE\ResultConverter(),
                new WhisperResponseConverter(),
                Contract\ResultConverter::create(),
            ],
            $contract ?? OpenAiContract::create(),
        );
    }
}
