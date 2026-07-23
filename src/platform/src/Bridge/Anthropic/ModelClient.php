<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;
    use JsonSchemaSanitizerTrait;
    use PromptCachingTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param 'none'|'short'|'long' $cacheRetention Controls Anthropic prompt-caching retention:
     *                                              - 'short': 5-minute cache window (default Anthropic ephemeral TTL)
     *                                              - 'long':  1-hour cache window; only available on api.anthropic.com
     *                                              - 'none':  prompt caching disabled
     * @param string                $baseUrl        Base URL of an Anthropic-compatible endpoint, without trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $cacheRetention = 'short',
        string $baseUrl = 'https://api.anthropic.com',
    ) {
        if (!\in_array($cacheRetention, ['none', 'short', 'long'], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid cache retention "%s". Supported values are "none", "short" and "long".', $cacheRetention));
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        $cacheControl = $this->getCacheControl($this->cacheRetention);
        $payload = $this->injectMessagesCacheControl($payload, $cacheControl);
        $payload = $this->injectSystemCacheControl($payload, $cacheControl);

        if (isset($options['server_tools'])) {
            $options['tools'] = [...$options['tools'] ?? [], ...$options['server_tools']];
            unset($options['server_tools']);
        }

        if (isset($options['tools'])) {
            $options['tool_choice'] ??= ['type' => 'auto'];
            $options['tools'] = $this->injectToolsCacheControl($options['tools'], $cacheControl);
        }

        // Adaptive thinking enables interleaved thinking on its own; the beta
        // header is only needed for the legacy enabled/budget_tokens format.
        if ('enabled' === ($options['thinking']['type'] ?? null)) {
            $options['beta_features'][] = 'interleaved-thinking-2025-05-14';
        }

        if (isset($options['response_format'])) {
            $schema = $options['response_format']['json_schema']['schema'] ?? [];
            $options['output_config']['format'] = [
                'type' => 'json_schema',
                'schema' => \is_array($schema) ? $this->normalizeStructuredOutputSchema($schema) : $schema,
            ];
            unset($options['response_format']);
        }

        if (isset($options['beta_features']) && \is_array($options['beta_features']) && \count($options['beta_features']) > 0) {
            $headers['anthropic-beta'] = implode(',', $options['beta_features']);
            unset($options['beta_features']);
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/messages', [
            'headers' => $headers,
            'body' => $this->encodeJsonBody(array_merge($options, $payload)),
        ]));
    }
}
