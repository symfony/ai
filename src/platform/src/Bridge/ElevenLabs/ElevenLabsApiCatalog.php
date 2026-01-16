<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabsApiCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $hostUrl = 'https://api.elevenlabs.io/v1',
    ) {
    }

    public function getModel(string $modelName): ElevenLabs
    {
        $models = $this->getModels();

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('The model "%s" cannot be retrieved from the API.', $modelName));
        }

        if ([] === $models[$modelName]['capabilities']) {
            throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the ElevenLabs API.', $modelName));
        }

        return new ElevenLabs($modelName, $models[$modelName]['capabilities']);
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/models', $this->hostUrl), [
            'headers' => [
                'xi-api-key' => $this->apiKey,
            ],
        ]);

        $models = $response->toArray();

        $capabilities = static fn (array $model): array => match (true) {
            $model['can_do_text_to_speech'] => [
                Capability::TEXT_TO_SPEECH,
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
            ],
            $model['can_do_voice_conversion'] => [
                Capability::SPEECH_TO_TEXT,
                Capability::INPUT_AUDIO,
                Capability::OUTPUT_TEXT,
            ],
            default => [],
        };

        return array_combine(
            array_map(static fn (array $model): string => $model['model_id'], $models),
            array_map(static fn (array $model): array => [
                'class' => ElevenLabs::class,
                'capabilities' => $capabilities($model),
            ], $models),
        );
    }
}
