<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
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

    public function getModel(string $modelName): Model
    {
        $models = $this->getModels();

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('Model "%s" does not exist.', $modelName));
        }

        return new Deepgram($modelName, $models[$modelName]['capabilities']);
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', 'models');

        $models = $response->toArray();

        if ([] === $models['tts'] && [] === $models['stt']) {
            return [];
        }

        $ttsModels = array_map(
            static fn (array $model): array => [
                $model['canonical_name'] => [
                    'class' => Deepgram::class,
                    'capabilities' => [
                        Capability::INPUT_TEXT,
                        Capability::TEXT_TO_SPEECH,
                        Capability::OUTPUT_AUDIO,
                    ],
                ],
            ],
            $models['tts'],
        );

        $sttModels = array_map(
            static fn (array $model): array => [
                $model['canonical_name'] => [
                    'class' => Deepgram::class,
                    'capabilities' => [
                        Capability::INPUT_AUDIO,
                        Capability::SPEECH_TO_TEXT,
                        Capability::OUTPUT_TEXT,
                    ],
                ],
            ],
            $models['stt'],
        );

        return [
            ...array_merge(...$ttsModels),
            ...array_merge(...$sttModels),
        ];
    }
}
