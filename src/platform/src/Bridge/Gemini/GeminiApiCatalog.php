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

        $capabilities = static function (array $model): array {
            $name = new UnicodeString($model['name']);
            $methods = $model['supportedGenerationMethods'] ?? [];

            if (\in_array('embedContent', $methods, true) || \in_array('asyncBatchEmbedContent', $methods, true)) {
                return [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                    Capability::OUTPUT_EMBEDDINGS,
                ];
            }

            if ($name->containsAny(['TTS', 'tts', 'native-audio'])) {
                return [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ];
            }

            if ($name->containsAny(['image'])) {
                return [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ];
            }

            $capabilities = [];

            if (\in_array('generateContent', $methods, true) || \in_array('batchGenerateContent', $methods, true) || \in_array('generateAnswer', $methods, true) || \in_array('predictLongRunning', $methods, true)) {
                $capabilities = [
                    Capability::INPUT_MESSAGES,
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

            if ($model['thinking'] ?? false) {
                $capabilities[] = Capability::THINKING;
            }

            if (\in_array('createCachedContent', $methods, true)) {
                $capabilities[] = Capability::CACHE;
            }

            return $capabilities;
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
