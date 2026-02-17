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
                'capabilities' => self::resolveTextCapabilities($model['model_spec']['capabilities'] ?? []),
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
                'capabilities' => match ($model['model_spec']['constraints']['model_type']) {
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
                    default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported. ', $model['id'])),
                },
            ],
            'image' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::TEXT_TO_IMAGE,
                    Capability::INPUT_TEXT,
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
