<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Dynamic ModelCatalog backed by the Gemini "models" REST API.
 *
 * @see https://ai.google.dev/api/models
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: class-string, capabilities: list<Capability>, version: ?string, inputTokenLimit: ?int, outputTokenLimit: ?int}>|null
     */
    private ?array $models = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Gemini
    {
        $models = $this->getModels();

        if (!\array_key_exists($modelName, $models)) {
            throw new ModelNotFoundException(\sprintf('Model "%s" not found in the Gemini API catalog.', $modelName));
        }

        $config = $models[$modelName];

        if ([] === $config['capabilities']) {
            throw new ModelNotFoundException(\sprintf('Model "%s" has no supported capabilities exposed by the Gemini API.', $modelName));
        }

        return new Gemini(
            $modelName,
            $config['capabilities'],
            [],
            $config['version'],
            $config['inputTokenLimit'],
            $config['outputTokenLimit'],
        );
    }

    public function getModels(): array
    {
        if (null !== $this->models) {
            return $this->models;
        }

        $catalog = [];
        $pageToken = null;

        do {
            $query = ['pageSize' => 1000];
            if (null !== $pageToken) {
                $query['pageToken'] = $pageToken;
            }

            try {
                $response = $this->httpClient->request('GET', 'models', ['query' => $query]);
            } catch (TransportExceptionInterface $e) {
                throw new RuntimeException(\sprintf('Cannot connect to the Gemini API: "%s".', $e->getMessage()), previous: $e);
            }

            $payload = $this->extractPayload($response);

            foreach ($payload['models'] ?? [] as $model) {
                $key = str_starts_with($model['name'], 'models/') ? substr($model['name'], 7) : $model['name'];

                $catalog[$key] = [
                    'class' => Gemini::class,
                    'capabilities' => self::deriveCapabilities($model),
                    'version' => $model['version'] ?? null,
                    'inputTokenLimit' => $model['inputTokenLimit'] ?? null,
                    'outputTokenLimit' => $model['outputTokenLimit'] ?? null,
                ];
            }

            $pageToken = $payload['nextPageToken'] ?? null;
        } while (null !== $pageToken);

        return $this->models = $catalog;
    }

    /**
     * @return array{models?: list<array<string, mixed>>, nextPageToken?: string}
     */
    private function extractPayload(ResponseInterface $response): array
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot connect to the Gemini API: "%s".', $e->getMessage()), previous: $e);
        }

        if (200 !== $statusCode) {
            $errorMessage = $this->extractErrorMessage($response);

            throw new RuntimeException(null !== $errorMessage ? \sprintf('Cannot retrieve models from the Gemini API (Status code: %d): "%s".', $statusCode, $errorMessage) : \sprintf('Cannot retrieve models from the Gemini API (Status code: %d).', $statusCode));
        }

        return $response->toArray();
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface) {
            return null;
        }

        if ('' === $content) {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($decoded) && isset($decoded['error']['message'])) {
                return $decoded['error']['message'];
            }
        } catch (\JsonException) {
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $model
     *
     * @return list<Capability>
     */
    private static function deriveCapabilities(array $model): array
    {
        $methods = $model['supportedGenerationMethods'] ?? [];
        $name = $model['name'] ?? '';

        if ([] !== array_intersect(['embedContent', 'asyncBatchEmbedContent', 'batchEmbedContents'], $methods)) {
            return [
                Capability::INPUT_TEXT,
                Capability::EMBEDDINGS,
                Capability::OUTPUT_EMBEDDINGS,
            ];
        }

        if (str_contains($name, 'tts') || str_contains($name, 'TTS') || str_contains($name, 'native-audio')) {
            return [
                Capability::INPUT_TEXT,
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ];
        }

        if (str_contains($name, 'image') || str_contains($name, 'imagen')) {
            return [
                Capability::INPUT_MESSAGES,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_IMAGE,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STRUCTURED,
            ];
        }

        $capabilities = [];

        if ([] !== array_intersect(['generateContent', 'batchGenerateContent', 'generateAnswer', 'predictLongRunning'], $methods)) {
            $capabilities = [
                Capability::INPUT_MESSAGES,
                Capability::INPUT_TEXT,
                Capability::INPUT_IMAGE,
                Capability::INPUT_AUDIO,
                Capability::INPUT_PDF,
                Capability::INPUT_VIDEO,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::OUTPUT_STRUCTURED,
                Capability::TOOL_CALLING,
            ];
        }

        if (true === ($model['thinking'] ?? false)) {
            $capabilities[] = Capability::THINKING;
        }

        if (\in_array('createCachedContent', $methods, true)) {
            $capabilities[] = Capability::CACHE;
        }

        return $capabilities;
    }
}
