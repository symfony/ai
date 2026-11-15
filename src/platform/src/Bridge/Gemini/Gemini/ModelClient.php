<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Roy Garrido
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    public const BATCH = 'batch';

    private const MAX_INLINE_BATCH_BYTES = 20_000_000;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Gemini-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://generativelanguage.googleapis.com',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gemini;
    }

    /**
     * @throws TransportExceptionInterface When the HTTP request fails due to network issues
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        if ($options[self::BATCH] ?? false) {
            unset($options[self::BATCH]);

            return $this->submitBatch($model, $payload, $options);
        }

        $url = \sprintf(
            '%s/v1beta/models/%s:%s',
            $this->baseUrl,
            $model->getName(),
            $options['stream'] ?? false ? 'streamGenerateContent?alt=sse' : 'generateContent',
        );

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody($this->createBody($payload, $options)),
        ]));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function createBody(array $payload, array $options): array
    {
        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $options['responseMimeType'] = 'application/json';
            $options['responseJsonSchema'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'];
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        // generationConfig expects the camelCase "maxOutputTokens" and rejects unknown fields
        if (isset($options[PlatformOptions::MAX_OUTPUT_TOKENS])) {
            $options['maxOutputTokens'] ??= $options[PlatformOptions::MAX_OUTPUT_TOKENS];
            unset($options[PlatformOptions::MAX_OUTPUT_TOKENS]);
        }

        $config = ['generationConfig' => $options];
        unset($config['generationConfig']['stream']);
        unset($config['generationConfig']['tools']);
        unset($config['generationConfig']['tool_config']);
        unset($config['generationConfig']['service_tier']);
        unset($config['generationConfig']['server_tools']);

        if ([] === $config['generationConfig']) {
            $config = [];
        }

        if (isset($options['tools'])) {
            $config['tools'][] = ['functionDeclarations' => $options['tools']];
            unset($options['tools']);
        }

        if (isset($options['tool_config'])) {
            $config['tool_config'] = $options['tool_config'];
            unset($options['tool_config']);
        }

        if (isset($options['service_tier'])) {
            $config['service_tier'] = $options['service_tier'];
        }

        foreach ($options['server_tools'] ?? [] as $tool => $params) {
            if (!$params) {
                continue;
            }

            $config['tools'][] = [$tool => true === $params ? new \ArrayObject() : $params];
        }

        return array_merge($config, $payload);
    }

    /**
     * Turns the normalized inputs into one request each, keyed by the identifier to report it back under.
     *
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function submitBatch(Model $model, array $payload, array $options): RawHttpResult
    {
        if ([] === $payload) {
            throw new InvalidArgumentException('A batch invocation expects a non-empty array of inputs.');
        }

        if ($options['stream'] ?? false) {
            throw new InvalidArgumentException('A batch is answered hours later, so it cannot be streamed.');
        }

        $requests = [];

        foreach ($payload as $key => $input) {
            if (\in_array($key, ['contents', 'system_instruction', 'systemInstruction'], true)) {
                throw new InvalidArgumentException('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');
            }

            if (!\is_array($input) || !\is_array($input['contents'] ?? null)) {
                throw new InvalidArgumentException(\sprintf('The input "%s" of the batch did not normalize into a request, a batch takes the same inputs as any other invocation - a "%s", for instance - one per identifier.', $key, MessageBag::class));
            }

            $requests[] = [
                'request' => $this->createBody($input, $options),
                'metadata' => ['key' => (string) $key],
            ];
        }

        $body = $this->encodeJsonBody([
            'batch' => [
                'displayName' => 'symfony-ai-'.bin2hex(random_bytes(4)),
                'inputConfig' => ['requests' => ['requests' => $requests]],
            ],
        ]);

        if (self::MAX_INLINE_BATCH_BYTES < \strlen($body)) {
            throw new InvalidArgumentException(\sprintf('The inputs of the batch take %d bytes, but Gemini accepts at most %d bytes inline. Split them into several batches.', \strlen($body), self::MAX_INLINE_BATCH_BYTES));
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/v1beta/models/%s:batchGenerateContent', $this->baseUrl, $model->getName()), [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
        ]));
    }
}
