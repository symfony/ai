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

use Symfony\AI\Platform\Bridge\Anthropic\Batch\BatchClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Message\MessageBag;
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

    public const BATCH = 'batch';

    /**
     * Anthropic requires a versioned `type` per server tool, so only tools whose
     * result blocks the converter can round-trip are mapped here. Anything else
     * stays reachable through the raw `tools` option.
     *
     * @var array<string, array{type: string, name: string}>
     */
    private const SERVER_TOOLS = [
        'web_search' => ['type' => 'web_search_20250305', 'name' => 'web_search'],
        'code_execution' => ['type' => 'code_execution_20250825', 'name' => 'code_execution'],
    ];

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;
    private readonly BatchClient $batchClient;

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
        $this->batchClient = new BatchClient($this->httpClient, $apiKey, $this->baseUrl);
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

        if ($options[self::BATCH] ?? false) {
            unset($options[self::BATCH]);

            return $this->submitBatch($payload, $options);
        }

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        ['body' => $body, 'beta_features' => $betaFeatures] = $this->createRequest($payload, $options);

        if ([] !== $betaFeatures) {
            $headers['anthropic-beta'] = implode(',', $betaFeatures);
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/messages', [
            'headers' => $headers,
            'body' => $this->encodeJsonBody($body),
        ]));
    }

    /**
     * The request body one input becomes, and the beta features Anthropic wants a header for.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array{body: array<string, mixed>, beta_features: list<string>}
     */
    private function createRequest(array $payload, array $options): array
    {
        $cacheControl = $this->getCacheControl($this->cacheRetention);
        $payload = $this->injectMessagesCacheControl($payload, $cacheControl);
        $payload = $this->injectSystemCacheControl($payload, $cacheControl);

        if (isset($options['tools'])) {
            $options['tool_choice'] ??= ['type' => 'auto'];
            $options['tools'] = $this->injectToolsCacheControl($options['tools'], $cacheControl);
        }

        if (isset($options['server_tools'])) {
            $serverTools = $this->mapServerTools($options['server_tools']);
            unset($options['server_tools']);

            if ([] !== $serverTools) {
                $options['tools'] = array_merge($options['tools'] ?? [], $serverTools);
                $options['tool_choice'] ??= ['type' => 'auto'];
            }
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

        $betaFeatures = [];

        if (isset($options['beta_features']) && \is_array($options['beta_features']) && \count($options['beta_features']) > 0) {
            $betaFeatures = array_values($options['beta_features']);
            unset($options['beta_features']);
        }

        return ['body' => array_merge($options, $payload), 'beta_features' => $betaFeatures];
    }

    /**
     * Turns the normalized inputs into one request body each, keyed by the identifier to report it
     * back under.
     *
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function submitBatch(array $payload, array $options): RawHttpResult
    {
        if ([] === $payload) {
            throw new InvalidArgumentException('A batch invocation expects a non-empty array of inputs.');
        }

        if ($options['stream'] ?? false) {
            throw new InvalidArgumentException('A batch is answered hours later, so it cannot be streamed.');
        }

        $requests = [];
        $betaFeatures = [];

        foreach ($payload as $customId => $input) {
            // A single input normalizes into the keys of one Messages request, not into a map of them.
            if (\in_array($customId, ['messages', 'system', 'model'], true)) {
                throw new InvalidArgumentException('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');
            }

            if (!\is_array($input) || !\is_array($input['messages'] ?? null)) {
                throw new InvalidArgumentException(\sprintf('The input "%s" of the batch did not normalize into a request, a batch takes the same inputs as any other invocation - a "%s", for instance - one per identifier.', $customId, MessageBag::class));
            }

            ['body' => $body, 'beta_features' => $features] = $this->createRequest($input, $options);

            $requests[$customId] = $body;
            $betaFeatures = array_merge($betaFeatures, $features);
        }

        // The beta features are headed on the batch as a whole, so a request needing one enables it
        // for all of them - Anthropic has no per-request header.
        return $this->batchClient->submit($requests, array_values(array_unique($betaFeatures)));
    }

    /**
     * @param array<string, array<string, mixed>|bool|null> $serverTools Name => params, `true` for "enabled, no params", falsy to skip
     *
     * @return list<array<string, mixed>>
     */
    private function mapServerTools(array $serverTools): array
    {
        $tools = [];

        foreach ($serverTools as $tool => $params) {
            if (!$params) {
                continue;
            }

            if (!isset(self::SERVER_TOOLS[$tool])) {
                throw new InvalidArgumentException(\sprintf('Unsupported Anthropic server tool "%s". Supported server tools are "%s". Use the "tools" option to pass an unmapped tool through verbatim.', $tool, implode('", "', array_keys(self::SERVER_TOOLS))));
            }

            $tools[] = \is_array($params) ? array_merge(self::SERVER_TOOLS[$tool], $params) : self::SERVER_TOOLS[$tool];
        }

        return $tools;
    }
}
