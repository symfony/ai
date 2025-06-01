<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral;

use Symfony\AI\Platform\Bridge\Mistral\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings\ModelClient as EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings\ResponseConverter as EmbeddingsResponseConverter;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ModelClient as MistralModelClient;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ResponseConverter as MistralResponseConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter]
        string $apiKey,
        ?HttpClientInterface $httpClient = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [new EmbeddingsModelClient($httpClient, $apiKey), new MistralModelClient($httpClient, $apiKey)],
            [new EmbeddingsResponseConverter(), new MistralResponseConverter()],
            Contract::create(new ToolNormalizer()),
        );
    }
}
