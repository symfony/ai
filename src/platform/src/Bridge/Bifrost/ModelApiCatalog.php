<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost;

use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechModel;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionModel;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageModel;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Dynamic Bifrost model catalogue. The list of available models is lazily
 * fetched from the `GET /v1/models` endpoint of the underlying Bifrost
 * instance the first time a lookup is performed. If the API call fails or
 * returns no usable payload, the catalogue silently falls back to a naming
 * convention so consumers can still use the bridge.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelApiCatalog extends AbstractModelCatalog
{
    private bool $modelsAreLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        try {
            return parent::getModel($modelName);
        } catch (ModelNotFoundException) {
            return $this->createFallbackModel($modelName);
        }
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
        $this->modelsAreLoaded = true;

        try {
            $response = $this->httpClient->request('GET', '/v1/models');

            if (200 !== $response->getStatusCode()) {
                return;
            }

            $payload = $response->toArray(false);
        } catch (HttpExceptionInterface) {
            return;
        }

        if (!isset($payload['data']) || !\is_array($payload['data'])) {
            return;
        }

        foreach ($payload['data'] as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $id = $entry['id'] ?? null;
            if (!\is_string($id) || '' === $id) {
                continue;
            }

            $class = self::resolveModelClass($id, $entry);
            $capabilities = self::resolveCapabilities($entry, $class);

            $this->models[$id] = [
                'class' => $class,
                'capabilities' => $capabilities,
            ];
        }
    }

    private function createFallbackModel(string $modelName): Model
    {
        $parsed = $this->parseModelName($modelName);
        $name = $parsed['name'];

        if ('' === $name) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $class = self::resolveModelClass($name);

        return new $class($name, Capability::cases(), $parsed['options']);
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return class-string<Model>
     */
    private static function resolveModelClass(string $id, array $entry = []): string
    {
        $outputs = self::extractStringList($entry, 'architecture', 'output_modalities');
        $inputs = self::extractStringList($entry, 'architecture', 'input_modalities');

        if (\in_array('image', $outputs, true)) {
            return ImageModel::class;
        }
        if (\in_array('audio', $outputs, true) && !\in_array('audio', $inputs, true)) {
            return SpeechModel::class;
        }
        if (\in_array('audio', $inputs, true) && !\in_array('audio', $outputs, true)) {
            return TranscriptionModel::class;
        }

        $needle = strtolower($id);
        if (str_contains($needle, 'embed')) {
            return EmbeddingsModel::class;
        }
        if (str_contains($needle, 'whisper') || str_contains($needle, 'transcribe') || str_contains($needle, '-stt')) {
            return TranscriptionModel::class;
        }
        if (str_contains($needle, 'tts') || str_ends_with($needle, '-speech')) {
            return SpeechModel::class;
        }
        if (
            str_contains($needle, 'dall-e')
            || str_contains($needle, 'imagen')
            || str_contains($needle, 'flux')
            || str_contains($needle, 'gpt-image')
            || str_contains($needle, 'stable-diffusion')
        ) {
            return ImageModel::class;
        }

        return CompletionsModel::class;
    }

    /**
     * @param array<array-key, mixed> $entry
     * @param class-string<Model>     $class
     *
     * @return list<Capability>
     */
    private static function resolveCapabilities(array $entry, string $class): array
    {
        $capabilities = [];
        $inputs = self::extractStringList($entry, 'architecture', 'input_modalities');
        $outputs = self::extractStringList($entry, 'architecture', 'output_modalities');

        foreach ($inputs as $modality) {
            $capability = match ($modality) {
                'text' => Capability::INPUT_TEXT,
                'image' => Capability::INPUT_IMAGE,
                'audio' => Capability::INPUT_AUDIO,
                'file' => Capability::INPUT_PDF,
                'video' => Capability::INPUT_VIDEO,
                default => null,
            };
            if (null !== $capability) {
                $capabilities[] = $capability;
            }
        }

        foreach ($outputs as $modality) {
            $capability = match ($modality) {
                'text' => Capability::OUTPUT_TEXT,
                'image' => Capability::OUTPUT_IMAGE,
                'audio' => Capability::OUTPUT_AUDIO,
                default => null,
            };
            if (null !== $capability) {
                $capabilities[] = $capability;
            }
        }

        if (CompletionsModel::class === $class) {
            $capabilities[] = Capability::OUTPUT_STREAMING;

            $supportedParameters = $entry['supported_parameters'] ?? null;
            if (\is_array($supportedParameters) && \in_array('tools', $supportedParameters, true)) {
                $capabilities[] = Capability::TOOL_CALLING;
            }
            if (\is_array($supportedParameters) && \in_array('structured_outputs', $supportedParameters, true)) {
                $capabilities[] = Capability::OUTPUT_STRUCTURED;
            }
        }

        if (EmbeddingsModel::class === $class) {
            $capabilities[] = Capability::EMBEDDINGS;
        }
        if (TranscriptionModel::class === $class) {
            $capabilities[] = Capability::SPEECH_TO_TEXT;
        }
        if (SpeechModel::class === $class) {
            $capabilities[] = Capability::TEXT_TO_SPEECH;
        }
        if (ImageModel::class === $class) {
            $capabilities[] = Capability::TEXT_TO_IMAGE;
        }

        return array_values(array_unique($capabilities, \SORT_REGULAR));
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return list<string>
     */
    private static function extractStringList(array $entry, string $parentKey, string $childKey): array
    {
        $parent = $entry[$parentKey] ?? null;
        if (!\is_array($parent)) {
            return [];
        }

        $child = $parent[$childKey] ?? null;
        if (!\is_array($child)) {
            return [];
        }

        $result = [];
        foreach ($child as $value) {
            if (\is_string($value) && '' !== $value) {
                $result[] = $value;
            }
        }

        return $result;
    }
}
