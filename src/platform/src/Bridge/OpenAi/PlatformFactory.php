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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PlatformFactory
{
    public const REGION_EU = 'EU';
    public const REGION_US = 'US';

    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?string $region = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new Gpt\ModelClient($httpClient, $apiKey, $region),
                new Embeddings\ModelClient($httpClient, $apiKey, $region),
                new DallE\ModelClient($httpClient, $apiKey, $region),
                new TextToSpeech\ModelClient($httpClient, $apiKey, $region),
                new Whisper\ModelClient($httpClient, $apiKey, $region),
            ],
            [
                new Gpt\ResultConverter(),
                new Embeddings\ResultConverter(),
                new DallE\ResultConverter(),
                new TextToSpeech\ResultConverter(),
                new Whisper\ResultConverter(),
            ],
            $modelCatalog,
            $contract ?? OpenAiContract::create(),
            $eventDispatcher,
        );
    }
}
