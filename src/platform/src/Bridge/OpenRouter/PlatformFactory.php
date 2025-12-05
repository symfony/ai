<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Gemini\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Contract\UserMessageNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author rglozman
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new Embeddings\ModelClient($httpClient, $apiKey), new Completions\ModelClient($httpClient, $apiKey)],
            [new Embeddings\ResultConverter(), new Completions\ResultConverter()],
            $modelCatalog,
            $contract ?? Contract::create(
                new AssistantMessageNormalizer(),
                new MessageBagNormalizer(),
                new UserMessageNormalizer(),
            ),
            $eventDispatcher,
        );
    }
}
