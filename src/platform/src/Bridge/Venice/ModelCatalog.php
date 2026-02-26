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

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('The model "%s" cannot be retrieved from the API.', $modelName));
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

        if ([] === $models['data']) {
            return [];
        }

        $payload = static fn (array $model): array => match ($model['type']) {
            'asr' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::SPEECH_RECOGNITION,
                    Capability::INPUT_TEXT,
                ],
            ],
            'embedding' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::EMBEDDINGS,
                    Capability::INPUT_TEXT,
                ],
            ],
            'text' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MESSAGES,
                ],
            ],
            'tts' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::TEXT_TO_SPEECH,
                    Capability::INPUT_TEXT,
                ],
            ],
            'video' => [
                'class' => Venice::class,
                'capabilities' => [
                    Capability::IMAGE_TO_VIDEO,
                    Capability::INPUT_IMAGE,
                ],
            ],
        };

        return array_combine(
            array_map(static fn (array $model): string => $model['id'], $models['data']),
            array_map(static fn (array $model): array => $payload($model), $models['data']),
        );
    }
}
