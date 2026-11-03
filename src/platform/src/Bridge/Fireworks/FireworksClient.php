<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FireworksClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $inferenceEndpoint = Factory::DEFAULT_INFERENCE_ENDPOINT,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Fireworks;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        return match (true) {
            $model->supports(Capability::RERANKING) => $this->requestRerank($model, $payload, $options, $httpOptions),
            $model->supports(Capability::EMBEDDINGS) => $this->requestEmbeddings($model, $payload, $options, $httpOptions),
            $model->supports(Capability::INPUT_MESSAGES) => $this->requestChatCompletions($model, $payload, $options, $httpOptions),
            default => throw new InvalidArgumentException(\sprintf('Unsupported model "%s": "%s".', $model::class, $model->getName())),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function requestChatCompletions(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->inferenceEndpoint.'/v1/chat/completions', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody(array_merge($options, $payload)),
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     * @param array<string, mixed>            $httpOptions
     */
    private function requestEmbeddings(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->inferenceEndpoint.'/v1/embeddings', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody(array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ])),
        ] + $httpOptions));
    }

    /**
     * @param array{query: string, documents: list<string>}|string $payload
     * @param array<string, mixed>                                 $options
     * @param array<string, mixed>                                 $httpOptions
     */
    private function requestRerank(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['query'], $payload['documents'])) {
            throw new InvalidArgumentException('Rerank payload must be an array with "query" and "documents" keys.');
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->inferenceEndpoint.'/v1/rerank', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody(array_merge($options, [
                'model' => $model->getName(),
                'query' => $payload['query'],
                'documents' => $payload['documents'],
            ])),
        ] + $httpOptions));
    }
}
