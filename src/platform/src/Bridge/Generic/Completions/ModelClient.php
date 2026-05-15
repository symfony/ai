<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Completions;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This default implementation is based on OpenAI's initial completion endpoint, that got later adopted by other
 * providers as well. It can be used by any bridge or directly with the default Factory.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $path = '/v1/chat/completions',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof CompletionsModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        // cacheRetention is an internal Symfony AI option consumed by PromptCacheNormalizer
        // (Anthropic-only).  Strip it here so it is never forwarded to OpenAI-compatible
        // endpoints, which reject unknown request body fields with a 400 error.
        unset($options['cacheRetention']);

        // Encode the body explicitly with JSON_UNESCAPED_SLASHES instead of relying on the
        // HttpClient "json" option: its encoder escapes forward slashes, so a namespaced
        // model name like "qwen/qwen3.5-4b" goes on the wire as "qwen\/qwen3.5-4b", which
        // some OpenAI-compatible backends fail to match. The remaining flags mirror
        // Symfony's HttpClientTrait::jsonEncode() defaults.
        $body = json_encode(array_merge($options, $payload), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_PRESERVE_ZERO_FRACTION);

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$this->path, [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $body,
        ]));
    }
}
