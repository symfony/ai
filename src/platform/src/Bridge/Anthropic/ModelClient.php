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
    private readonly EventSourceHttpClient $httpClient;

    /**
     * @param 'none'|'short'|'long' $cacheRetention Controls Anthropic prompt-caching retention:
     *                                              - 'short': 5-minute cache window (default Anthropic ephemeral TTL)
     *                                              - 'long':  1-hour cache window; only available on api.anthropic.com
     *                                              - 'none':  prompt caching disabled
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $cacheRetention = 'short',
    ) {
        if (!\in_array($cacheRetention, ['none', 'short', 'long'], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid cache retention "%s". Supported values are "none", "short" and "long".', $cacheRetention));
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
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
        ];

        $payload = $this->injectCacheControl($payload);

        if (isset($options['tools'])) {
            $options['tool_choice'] = ['type' => 'auto'];
            $options['tools'] = $this->injectToolsCacheControl($options['tools']);
        }

        if (isset($options['thinking'])) {
            $options['beta_features'][] = 'interleaved-thinking-2025-05-14';
        }

        if (isset($options['response_format'])) {
            $options['output_config'] = [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $options['response_format']['json_schema']['schema'] ?? [],
                ],
            ];
            unset($options['response_format']);
        }

        if (isset($options['beta_features']) && \is_array($options['beta_features']) && \count($options['beta_features']) > 0) {
            $headers['anthropic-beta'] = implode(',', $options['beta_features']);
            unset($options['beta_features']);
        }

        return new RawHttpResult($this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => $headers,
            'json' => array_merge($options, $payload),
        ]));
    }

    /**
     * Injects a prompt-caching marker on the last tool definition.
     *
     * This creates an additional cache breakpoint after all tool definitions,
     * so the prefix "system → tools" can be cached independently of the
     * messages that follow.  Tool definitions are typically identical across
     * requests, making this a very effective caching target.
     *
     * @param list<array<string, mixed>> $tools Normalised tool definitions
     *
     * @return list<array<string, mixed>>
     */
    private function injectToolsCacheControl(array $tools): array
    {
        if ('none' === $this->cacheRetention || [] === $tools) {
            return $tools;
        }

        $cacheControl = 'long' === $this->cacheRetention
            ? ['type' => 'ephemeral', 'ttl' => '1h']
            : ['type' => 'ephemeral'];

        $tools[\count($tools) - 1]['cache_control'] = $cacheControl;

        return $tools;
    }

    /**
     * Injects prompt-caching markers into the normalised message payload.
     *
     * Anthropic prompt caching requires a {"cache_control": {"type": "ephemeral"}}
     * annotation on the last block of the last user message.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function injectCacheControl(array $payload): array
    {
        if ('none' === $this->cacheRetention) {
            return $payload;
        }

        $messages = $payload['messages'] ?? [];

        if ([] === $messages) {
            return $payload;
        }

        $cacheControl = 'long' === $this->cacheRetention
            ? ['type' => 'ephemeral', 'ttl' => '1h']
            : ['type' => 'ephemeral'];

        for ($i = \count($messages) - 1; $i >= 0; --$i) {
            if ('user' !== ($messages[$i]['role'] ?? '')) {
                continue;
            }

            $content = $messages[$i]['content'] ?? null;

            if (\is_string($content)) {
                $messages[$i]['content'] = [
                    ['type' => 'text', 'text' => $content, 'cache_control' => $cacheControl],
                ];
                break;
            }

            if (\is_array($content) && [] !== $content) {
                $lastIdx = \count($content) - 1;
                if (\is_array($content[$lastIdx])) {
                    $content[$lastIdx]['cache_control'] = $cacheControl;
                    $messages[$i]['content'] = $content;
                }
                break;
            }
        }

        $payload['messages'] = $messages;

        return $payload;
    }
}
