<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TogetherClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Together;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        return match (true) {
            $model->supports(Capability::INPUT_MESSAGES) => $this->doCompletionsRequest($payload, $options, $httpOptions),
            $model->supports(Capability::EMBEDDINGS) => $this->doEmbeddingsRequest($model, $payload, $options, $httpOptions),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeechRequest($model, $payload, $options, $httpOptions),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToTextRequest($model, $payload, $options, $httpOptions),
            $model->supports(Capability::OUTPUT_IMAGE) => $this->doImageGenerationRequest($model, $payload, $options, $httpOptions),
            $model->supports(Capability::RERANKING) => $this->doRerankRequest($model, $payload, $options, $httpOptions),
            default => throw new InvalidArgumentException(\sprintf('Unsupported model "%s": "%s".', $model::class, $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     * @param array<string, mixed>            $httpOptions
     */
    private function doCompletionsRequest(array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        return new RawHttpResult($this->httpClient->request('POST', '/v1/chat/completions', [
            'json' => array_merge($options, $payload),
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     * @param array<string, mixed>            $httpOptions
     */
    private function doEmbeddingsRequest(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', '/v1/embeddings', [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     * @param array<string, mixed>            $httpOptions
     */
    private function doImageGenerationRequest(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', '/v1/images/generations', [
            'json' => array_merge(['response_format' => 'base64'], $options, [
                'model' => $model->getName(),
                'prompt' => $this->extractText($payload, 'prompt'),
            ]),
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     * @param array<string, mixed>            $httpOptions
     */
    private function doTextToSpeechRequest(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for text-to-speech.');
        }

        return new RawHttpResult($this->httpClient->request('POST', '/v1/audio/speech', [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $this->extractText($payload, 'input'),
            ]),
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     * @param array<string, mixed>            $httpOptions
     */
    private function doSpeechToTextRequest(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        // Together accepts a binary upload (resource) or a remote URL (string) as the "file".
        if (!\is_array($payload) || !isset($payload['file']) || (!\is_resource($payload['file']) && !\is_string($payload['file']))) {
            throw new InvalidArgumentException('The speech-to-text payload must contain a "file" key holding an audio resource or URL.');
        }

        $task = $options['task'] ?? 'transcription';
        unset($options['task']);

        $endpoint = 'translation' === $task ? '/v1/audio/translations' : '/v1/audio/transcriptions';

        return new RawHttpResult($this->httpClient->request('POST', $endpoint, [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => array_merge($options, [
                'model' => $model->getName(),
                'file' => $payload['file'],
            ]),
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     * @param array<string, mixed>            $httpOptions
     */
    private function doRerankRequest(Model $model, array|string $payload, array $options, array $httpOptions): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['query'])) {
            throw new InvalidArgumentException('The reranking payload must contain a "query" key.');
        }

        // Together expects "documents"; "texts" is accepted as an alias for consistency with other rerank bridges.
        $documents = $payload['documents'] ?? $payload['texts'] ?? null;
        if (null === $documents) {
            throw new InvalidArgumentException('The reranking payload must contain a "documents" (or "texts") key.');
        }

        return new RawHttpResult($this->httpClient->request('POST', '/v1/rerank', [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'query' => $payload['query'],
                'documents' => $documents,
            ]),
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     */
    private function extractText(array|string $payload, string $arrayKey): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        if (isset($payload[$arrayKey]) && \is_string($payload[$arrayKey])) {
            return $payload[$arrayKey];
        }

        if (isset($payload['text']) && \is_string($payload['text'])) {
            return $payload['text'];
        }

        throw new InvalidArgumentException(\sprintf('The payload must be a string or contain a "%s" key.', $arrayKey));
    }
}
