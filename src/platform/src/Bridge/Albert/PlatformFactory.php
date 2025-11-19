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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
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
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('The Albert URL must start with "https://".');
        }
        if (str_ends_with($baseUrl, '/')) {
            throw new InvalidArgumentException('The Albert URL must not end with a trailing slash.');
        }
        if (!preg_match('/\/v\d+$/', $baseUrl)) {
            throw new InvalidArgumentException('The Albert URL must include an API version (e.g., /v1, /v2).');
        }
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new GptModelClient($httpClient, $apiKey, $baseUrl),
                new EmbeddingsModelClient($httpClient, $apiKey, $baseUrl),
            ],
            [new Gpt\ResultConverter(), new Embeddings\ResultConverter()],
            $modelCatalog,
            Contract::create(),
            $eventDispatcher,
        );
    }
}
