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
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Dynamic catalog fetching the model list from the Together API (`GET /v1/models`).
 *
 * The endpoint only exposes a coarse `type` per model (chat, language, code, image,
 * embedding, moderation, rerank), so capabilities are derived from that type.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: class-string, capabilities: list<Capability>}>|null
     */
    private ?array $models = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Together
    {
        $models = $this->getModels();

        if (!\array_key_exists($modelName, $models)) {
            throw new ModelNotFoundException(\sprintf('Model "%s" not found.', $modelName));
        }

        return new Together($modelName, $models[$modelName]['capabilities']);
    }

    public function getModels(): array
    {
        if (null !== $this->models) {
            return $this->models;
        }

        $response = $this->httpClient->request('GET', '/v1/models');

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot connect to the Together API: "%s".', $e->getMessage()), previous: $e);
        }

        if (200 !== $statusCode) {
            throw new RuntimeException(\sprintf('Cannot retrieve models from the Together API (Status code: %d).', $statusCode));
        }

        $catalog = [];

        foreach ($response->toArray() as $model) {
            if (!\is_array($model)) {
                continue;
            }

            if (!isset($model['id'], $model['type']) || !\is_string($model['id']) || !\is_string($model['type'])) {
                continue;
            }

            $catalog[$model['id']] = [
                'class' => Together::class,
                'capabilities' => $this->capabilitiesForType($model['type']),
            ];
        }

        // The /v1/models endpoint does not expose an audio type, so TTS/STT models
        // are overlaid statically to make them discoverable with the right capabilities.
        return $this->models = array_merge($catalog, self::audioModels());
    }

    /**
     * Statically known audio models, since `GET /v1/models` has no audio/tts/stt type.
     *
     * @return array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    private static function audioModels(): array
    {
        $textToSpeech = [Capability::TEXT_TO_SPEECH, Capability::OUTPUT_AUDIO];
        $speechToText = [Capability::SPEECH_TO_TEXT, Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT];

        return [
            'cartesia/sonic' => ['class' => Together::class, 'capabilities' => $textToSpeech],
            'cartesia/sonic-2' => ['class' => Together::class, 'capabilities' => $textToSpeech],
            'hexgrad/Kokoro-82M' => ['class' => Together::class, 'capabilities' => $textToSpeech],
            'canopylabs/orpheus-3b-0.1-ft' => ['class' => Together::class, 'capabilities' => $textToSpeech],
            'openai/whisper-large-v3' => ['class' => Together::class, 'capabilities' => $speechToText],
        ];
    }

    /**
     * @return list<Capability>
     */
    private function capabilitiesForType(string $type): array
    {
        return match ($type) {
            'chat' => [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::OUTPUT_STRUCTURED,
                Capability::TOOL_CALLING,
            ],
            'language', 'code' => [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
            ],
            'image' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_IMAGE,
                Capability::TEXT_TO_IMAGE,
            ],
            'embedding' => [
                Capability::INPUT_TEXT,
                Capability::EMBEDDINGS,
            ],
            'rerank' => [
                Capability::INPUT_TEXT,
                Capability::RERANKING,
            ],
            'moderation' => [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
            ],
            default => [
                Capability::INPUT_TEXT,
            ],
        };
    }
}
