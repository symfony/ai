<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Inworld;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    private const TTS_CAPABILITIES = [
        Capability::TEXT_TO_SPEECH,
        Capability::INPUT_TEXT,
        Capability::OUTPUT_AUDIO,
    ];

    private const STT_CAPABILITIES = [
        Capability::SPEECH_TO_TEXT,
        Capability::INPUT_AUDIO,
        Capability::OUTPUT_TEXT,
    ];

    /**
     * @var array<string, array{class: class-string, capabilities: list<Capability>}>|null
     */
    private ?array $models = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Inworld
    {
        $models = $this->getModels();

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('The model "%s" cannot be retrieved from the API.', $modelName));
        }

        if ([] === $models[$modelName]['capabilities']) {
            throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Inworld API.', $modelName));
        }

        return new Inworld($modelName, $models[$modelName]['capabilities']);
    }

    public function getModels(): array
    {
        if (null !== $this->models) {
            return $this->models;
        }

        $response = $this->httpClient->request('GET', 'llm/v1alpha/models');

        $payload = $response->toArray(false);
        $models = $payload['models'] ?? null;

        if (!\is_array($models)) {
            return $this->models = [];
        }

        $result = [];

        foreach ($models as $model) {
            if (!\is_array($model)) {
                continue;
            }

            $name = $model['model'] ?? null;

            if (!\is_string($name) || '' === $name) {
                continue;
            }

            $result[$name] = [
                'class' => Inworld::class,
                'capabilities' => self::resolveCapabilities($model),
            ];
        }

        return $this->models = $result;
    }

    /**
     * @param array<string|int, mixed> $model
     *
     * @return list<Capability>
     */
    private static function resolveCapabilities(array $model): array
    {
        $spec = $model['spec'] ?? null;

        if (!\is_array($spec)) {
            return [];
        }

        $inputs = $spec['inputModalities'] ?? null;
        $outputs = $spec['outputModalities'] ?? null;

        if (!\is_array($inputs) || !\is_array($outputs)) {
            return [];
        }

        $hasText = \in_array('text', $inputs, true);
        $hasAudioIn = \in_array('audio', $inputs, true);
        $hasAudioOut = \in_array('audio', $outputs, true);
        $hasTextOut = \in_array('text', $outputs, true);

        if ($hasText && $hasAudioOut) {
            return self::TTS_CAPABILITIES;
        }

        if ($hasAudioIn && $hasTextOut) {
            return self::STT_CAPABILITIES;
        }

        return [];
    }
}
