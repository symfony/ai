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
use Symfony\AI\Platform\Bridge\OpenRouter\Completions\ModelClient as CompletionsModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\Completions\ResultConverter as CompletionsResultConverter;
use Symfony\AI\Platform\Bridge\OpenRouter\Embeddings\ModelClient as EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\Embeddings\ResultConverter as EmbeddingsResultConverter;
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
            [new EmbeddingsModelClient($httpClient, $apiKey), new CompletionsModelClient($httpClient, $apiKey)],
            [new EmbeddingsResultConverter(), new CompletionsResultConverter()],
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
