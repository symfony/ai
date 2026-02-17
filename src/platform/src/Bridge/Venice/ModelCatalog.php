<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Venice
    {
        $models = $this->getModels();

        if ([] === $models) {
            throw new InvalidArgumentException('No models available in the Venice catalog.');
        }

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('The model "%s" cannot be retrieved from the Venice API.', $modelName));
        }

        if ([] === $models[$modelName]['capabilities']) {
            throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Venice API.', $modelName));
        }

        return new Venice($modelName, $models[$modelName]['capabilities']);
    }

    public function getModels(): array
    {
        $results = $this->httpClient->request('GET', 'models', [
            'query' => [
                'type' => 'all',
            ],
        ]);

        $models = $results->toArray();

        if (!\is_array($models['data'] ?? null) || [] === $models['data']) {
            return [];
        }

        /** @var list<mixed> $modelsData */
        $modelsData = $models['data'];

        $payload = static fn (array $model): array => match ($model['type']) {
            'asr' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::SPEECH_TO_TEXT,
                    Capability::SPEECH_RECOGNITION,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'embedding' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::EMBEDDINGS,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_EMBEDDINGS,
                ],
            ],
            'text' => [
                'class' => Venice::class,
                'capabilities' => self::resolveTextCapabilities(
                    \is_array($model['model_spec'] ?? null) && \is_array($model['model_spec']['capabilities'] ?? null)
                        ? $model['model_spec']['capabilities']
                        : [],
                ),
            ],
            'tts' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'video' => [
                'class' => Venice::class,
                'capabilities' => self::resolveVideoCapabilities($model),
            ],
            'image' => [
                'class' => Venice::class,
                'capabilities' => self::resolveImageCapabilities($model),
            ],
            'upscale' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::IMAGE_TO_IMAGE,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            default => [
                'class' => Venice::class,
                'capabilities' => [],
            ],
        };

        $result = [];

        foreach ($modelsData as $model) {
            if (!\is_array($model) || !\is_string($model['id'] ?? null)) {
                continue;
            }

            $result[$model['id']] = $payload($model);
        }

        return $result;
    }

    /**
     * Resolves the model id behind a Venice "trait" alias such as `default`,
     * `default_reasoning`, `default_code`, `default_vision`, `most_intelligent`,
     * `most_uncensored`, `function_calling_default` or `fastest`.
     *
     * Returns null when the trait is not exposed by the API for the requested type.
     */
    public function resolveTrait(string $trait, string $type = 'text'): ?string
    {
        $results = $this->httpClient->request('GET', 'models/traits', [
            'query' => ['type' => $type],
        ]);

        $payload = $results->toArray();
        $traits = \is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        if (!\is_string($traits[$trait] ?? null)) {
            return null;
        }

        return $traits[$trait];
    }

    /**
     * @param array<int|string, mixed> $model
     *
     * @return list<Capability>
     */
    private static function resolveImageCapabilities(array $model): array
    {
        $modelSpec = \is_array($model['model_spec'] ?? null) ? $model['model_spec'] : [];
        $constraints = \is_array($modelSpec['constraints'] ?? null) ? $modelSpec['constraints'] : [];
        $modelType = \is_string($constraints['model_type'] ?? null) ? $constraints['model_type'] : 'text-to-image';

        if ('image-to-image' === $modelType || str_ends_with($modelType, '-edit')) {
            return [
                Capability::IMAGE_TO_IMAGE,
                Capability::INPUT_IMAGE,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_IMAGE,
            ];
        }

        return [
            Capability::TEXT_TO_IMAGE,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_IMAGE,
        ];
    }

    /**
     * @param array<int|string, mixed> $model
     *
     * @return list<Capability>
     */
    private static function resolveVideoCapabilities(array $model): array
    {
        $modelSpec = $model['model_spec'] ?? [];

        if (!\is_array($modelSpec)) {
            throw new InvalidArgumentException(\sprintf('The model "%s" is not supported.', \is_string($model['id'] ?? null) ? $model['id'] : 'unknown'));
        }

        $constraints = $modelSpec['constraints'] ?? [];

        if (!\is_array($constraints) || !\is_string($constraints['model_type'] ?? null)) {
            throw new InvalidArgumentException(\sprintf('The model "%s" is not supported.', \is_string($model['id'] ?? null) ? $model['id'] : 'unknown'));
        }

        return match ($constraints['model_type']) {
            'text-to-video' => [
                Capability::TEXT_TO_VIDEO,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_VIDEO,
            ],
            'image-to-video' => [
                Capability::IMAGE_TO_VIDEO,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_VIDEO,
            ],
            'video' => [
                Capability::VIDEO_TO_VIDEO,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_VIDEO,
            ],
            default => [],
        };
    }

    /**
     * @param array<string|int, mixed> $capabilities
     *
     * @return list<Capability>
     */
    private static function resolveTextCapabilities(array $capabilities): array
    {
        $resolved = [
            Capability::INPUT_TEXT,
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
        ];

        if ($capabilities['optimizedForCode'] ?? false) {
            $resolved[] = Capability::INPUT_CODE;
        }

        if ($capabilities['supportsFunctionCalling'] ?? false) {
            $resolved[] = Capability::TOOL_CALLING;
        }

        if ($capabilities['supportsReasoning'] ?? false) {
            $resolved[] = Capability::THINKING;
        }

        if ($capabilities['supportsVision'] ?? false) {
            $resolved[] = Capability::INPUT_IMAGE;
        }

        if ($capabilities['supportsAudioInput'] ?? false) {
            $resolved[] = Capability::INPUT_AUDIO;
        }

        if ($capabilities['supportsVideoInput'] ?? false) {
            $resolved[] = Capability::INPUT_VIDEO;
        }

        return $resolved;
    }
}
