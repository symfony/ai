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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class GeminiApiCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Gemini
    {
        $models = $this->getModels();

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('Model "%s" not found, please check the Gemini API.', $modelName));
        }

        if ([] === $models[$modelName]['capabilities']) {
            throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Gemini API.', $modelName));
        }

        $currentModel = $models[$modelName];

        return new Gemini($modelName, $currentModel['capabilities'], [], $currentModel['version'], $currentModel['inputTokenLimit'], $currentModel['outputTokenLimit']);
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', 'models');

        $models = $response->toArray();

        if ([] === $models['models']) {
            return [];
        }

        $capabilities = static fn (array $model): array => match (true) {
            (new UnicodeString($model['name']))->containsAny('tts') => [
                Capability::INPUT_TEXT,
                Capability::TEXT_TO_SPEECH,
                Capability::OUTPUT_AUDIO,
            ],
            \array_key_exists('description', $model) && (new UnicodeString($model['description']))->containsAny('Image') => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_IMAGE,
            ],
            \in_array('predictLongRunning', $model['supportedGenerationMethods'], true),
            \in_array('generateAnswer', $model['supportedGenerationMethods'], true),
            \in_array('batchGenerateContent', $model['supportedGenerationMethods'], true),
            \in_array('generateContent', $model['supportedGenerationMethods'], true) => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_TEXT,
                Capability::TOOL_CALLING,
            ],
            \in_array('embedContent', $model['supportedGenerationMethods'], true),
            \in_array('asyncBatchEmbedContent', $model['supportedGenerationMethods'], true) => [
                Capability::INPUT_TEXT,
                Capability::EMBEDDINGS,
                Capability::OUTPUT_EMBEDDINGS,
            ],
            \in_array('predict', $model['supportedGenerationMethods'], true) => [
                Capability::OUTPUT_IMAGE,
            ],
            \array_key_exists('thinking', $model) && $model['thinking'] => [
                Capability::THINKING,
            ],
            default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported.', $model['name'])),
        };

        return array_merge(...array_map(static fn (array $model): array => [
            (new UnicodeString($model['name']))->after('models/')->toString() => [
                'class' => Gemini::class,
                'capabilities' => $capabilities($model),
                'version' => $model['version'],
                'inputTokenLimit' => $model['inputTokenLimit'],
                'outputTokenLimit' => $model['outputTokenLimit'],
            ],
        ], $models['models']));
    }
}
