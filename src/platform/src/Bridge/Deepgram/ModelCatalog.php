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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves Deepgram models from the live `/v1/models` endpoint.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: class-string<Deepgram>, capabilities: list<Capability>}>|null
     */
    private ?array $cache = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Model
    {
        $models = $this->resolveCatalog();

        if (!\array_key_exists($modelName, $models)) {
            throw new InvalidArgumentException(\sprintf('Model "%s" does not exist.', $modelName));
        }

        return new Deepgram($modelName, $models[$modelName]['capabilities']);
    }

    public function getModels(): array
    {
        return $this->resolveCatalog();
    }

    /**
     * @return array<string, array{class: class-string<Deepgram>, capabilities: list<Capability>}>
     */
    private function resolveCatalog(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        try {
            $response = $this->httpClient->request('GET', 'models');
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw new RuntimeException('Could not reach the Deepgram API to fetch the model catalog.', 0, $exception);
        }

        if ($statusCode >= 400) {
            throw new RuntimeException(\sprintf('Deepgram returned status "%d" while listing models.', $statusCode));
        }

        try {
            $payload = $response->toArray();
        } catch (JsonException $exception) {
            throw new RuntimeException('Deepgram returned a malformed JSON payload while listing models.', 0, $exception);
        }

        $catalog = [];

        $tts = $payload['tts'] ?? [];
        if (\is_array($tts)) {
            foreach ($tts as $model) {
                if (!\is_array($model)) {
                    continue;
                }
                $this->indexModel($catalog, $model, [
                    Capability::INPUT_TEXT,
                    Capability::TEXT_TO_SPEECH,
                    Capability::OUTPUT_AUDIO,
                ]);
            }
        }

        $stt = $payload['stt'] ?? [];
        if (\is_array($stt)) {
            foreach ($stt as $model) {
                if (!\is_array($model)) {
                    continue;
                }
                $this->indexModel($catalog, $model, [
                    Capability::INPUT_AUDIO,
                    Capability::SPEECH_TO_TEXT,
                    Capability::OUTPUT_TEXT,
                ]);
            }
        }

        return $this->cache = $catalog;
    }

    /**
     * @param array<string, array{class: class-string<Deepgram>, capabilities: list<Capability>}> $catalog
     * @param array<int|string, mixed>                                                            $model
     * @param list<Capability>                                                                    $capabilities
     */
    private function indexModel(array &$catalog, array $model, array $capabilities): void
    {
        $entry = [
            'class' => Deepgram::class,
            'capabilities' => $capabilities,
        ];

        foreach (['name', 'canonical_name'] as $key) {
            $identifier = $model[$key] ?? null;
            if (\is_string($identifier) && '' !== $identifier) {
                $catalog[$identifier] = $entry;
            }
        }
    }
}
