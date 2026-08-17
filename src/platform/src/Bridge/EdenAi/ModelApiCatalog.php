<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Discovers every model Eden AI currently serves, rather than the curated subset of
 * ModelCatalog.
 *
 * Eden AI is a gateway fronting well over a thousand models, so a hand-written catalog can
 * only ever cover a fraction of them: any other identifier, though perfectly valid, fails
 * with a ModelNotFoundException. Three public endpoints are merged here, none of which
 * needs credentials:
 *
 *     GET /v3/models             the chat models, with their capability metadata
 *     GET /v3/embeddings/models  the embedding models
 *     GET /v3/info               every expert feature/subfeature and its provider models
 *
 * Discovery happens once, lazily, on the first lookup. Use ModelCatalog instead to pin a
 * known set of models and avoid the extra requests.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelApiCatalog extends AbstractModelCatalog
{
    /**
     * Maps an expert "feature/subfeature" onto the model class handling it, mirroring the
     * model clients and result converters the Factory registers. Subfeatures absent from
     * this map stay hidden: the bridge has no converter able to read their output, so
     * exposing them would only produce failures at conversion time.
     *
     * @var array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    private const EXPERT_SUBFEATURES = [
        'ocr/ocr' => [
            'class' => Ocr::class,
            'capabilities' => [Capability::INPUT_PDF, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT],
        ],
        'ocr/financial_parser' => [
            'class' => DocumentParser::class,
            'capabilities' => [Capability::INPUT_PDF, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'ocr/resume_parser' => [
            'class' => DocumentParser::class,
            'capabilities' => [Capability::INPUT_PDF, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'ocr/identity_parser' => [
            'class' => DocumentParser::class,
            'capabilities' => [Capability::INPUT_PDF, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'audio/tts' => [
            'class' => Tts::class,
            'capabilities' => [Capability::TEXT_TO_SPEECH],
        ],
        'audio/speech_to_text_async' => [
            'class' => SpeechToText::class,
            'capabilities' => [Capability::SPEECH_TO_TEXT],
        ],
        'image/object_detection' => [
            'class' => ImageAnalysis::class,
            'capabilities' => [Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'image/explicit_content' => [
            'class' => ImageAnalysis::class,
            'capabilities' => [Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'image/logo_detection' => [
            'class' => ImageAnalysis::class,
            'capabilities' => [Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'image/face_detection' => [
            'class' => ImageAnalysis::class,
            'capabilities' => [Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'image/ai_detection' => [
            'class' => ImageAnalysis::class,
            'capabilities' => [Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'image/deepfake_detection' => [
            'class' => ImageAnalysis::class,
            'capabilities' => [Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED],
        ],
        'image/generation' => [
            'class' => ImageGeneration::class,
            'capabilities' => [Capability::TEXT_TO_IMAGE],
        ],
    ];

    private bool $modelsAreLoaded = false;

    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $additionalModels
     *                                                                                                    registered on top of the discovered ones, and winning over them
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl = 'https://api.edenai.run',
        private readonly array $additionalModels = [],
    ) {
        $this->models = $additionalModels;
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        return parent::getModel($modelName);
    }

    public function getModels(): array
    {
        $this->preloadRemoteModels();

        return parent::getModels();
    }

    private function preloadRemoteModels(): void
    {
        if ($this->modelsAreLoaded) {
            return;
        }

        // Flagged upfront so a failed discovery is not retried on every single lookup.
        $this->modelsAreLoaded = true;

        $this->models = [
            ...$this->fetchLanguageModels(),
            ...$this->fetchEmbeddingModels(),
            ...$this->fetchExpertModels(),
            // Explicitly registered models win over the discovered ones.
            ...$this->additionalModels,
        ];

        ksort($this->models);
    }

    /**
     * @return array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    private function fetchLanguageModels(): array
    {
        $models = [];

        foreach ($this->request('/v3/models')['data'] ?? [] as $model) {
            if (!\is_array($model) || !isset($model['id']) || !\is_string($model['id'])) {
                continue;
            }

            $capabilities = $model['capabilities'] ?? [];

            $models[$model['id']] = [
                'class' => CompletionsModel::class,
                'capabilities' => $this->mapLanguageModelCapabilities(\is_array($capabilities) ? $capabilities : []),
            ];
        }

        return $models;
    }

    /**
     * @return array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    private function fetchEmbeddingModels(): array
    {
        $models = [];

        foreach ($this->request('/v3/embeddings/models')['data'] ?? [] as $model) {
            if (!\is_array($model) || !isset($model['id']) || !\is_string($model['id'])) {
                continue;
            }

            $models[$model['id']] = [
                'class' => EmbeddingsModel::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ];
        }

        return $models;
    }

    /**
     * @return array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    private function fetchExpertModels(): array
    {
        $models = [];

        foreach ($this->request('/v3/info')['features'] ?? [] as $feature) {
            if (!\is_array($feature) || !isset($feature['name']) || !\is_string($feature['name'])) {
                continue;
            }

            foreach ($feature['subfeatures'] ?? [] as $subfeature) {
                if (!\is_array($subfeature) || !isset($subfeature['name']) || !\is_string($subfeature['name'])) {
                    continue;
                }

                $definition = self::EXPERT_SUBFEATURES[$feature['name'].'/'.$subfeature['name']] ?? null;

                if (null === $definition) {
                    continue;
                }

                foreach ($subfeature['models'] ?? [] as $model) {
                    if (!\is_array($model) || !isset($model['model']) || !\is_string($model['model'])) {
                        continue;
                    }

                    $models[$model['model']] = $definition;
                }
            }
        }

        return $models;
    }

    /**
     * @param array<string, mixed> $capabilities the "capabilities" object of GET /v3/models
     *
     * @return list<Capability>
     */
    private function mapLanguageModelCapabilities(array $capabilities): array
    {
        $mapped = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ];

        if (true === ($capabilities['supports_function_calling'] ?? null)) {
            $mapped[] = Capability::TOOL_CALLING;
        }

        if (true === ($capabilities['supports_response_schema'] ?? null)) {
            $mapped[] = Capability::OUTPUT_STRUCTURED;
        }

        $modalities = $capabilities['input_modalities'] ?? [];

        if (!\is_array($modalities)) {
            return $mapped;
        }

        // "file" is how Eden AI advertises document input, which the platform calls INPUT_PDF.
        foreach ([
            'image' => Capability::INPUT_IMAGE,
            'file' => Capability::INPUT_PDF,
            'audio' => Capability::INPUT_AUDIO,
            'video' => Capability::INPUT_VIDEO,
        ] as $modality => $capability) {
            if (\in_array($modality, $modalities, true)) {
                $mapped[] = $capability;
            }
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $path): array
    {
        try {
            return $this->httpClient->request('GET', $this->baseUrl.$path)->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Could not discover the Eden AI models from "%s".', $path), 0, $e);
        }
    }
}
