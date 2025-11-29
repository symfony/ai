<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class OllamaApiCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly string $host,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Ollama
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/api/show', $this->host), [
            'json' => [
                'model' => $modelName,
            ],
        ]);

        $payload = $response->toArray();

        if ([] === $payload['capabilities']) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        $capabilities = array_map(
            static fn (string $capability): Capability => match ($capability) {
                'embedding' => Capability::EMBEDDINGS,
                'completion' => Capability::INPUT_MESSAGES,
                'tools' => Capability::TOOL_CALLING,
                'thinking' => Capability::THINKING,
                'vision' => Capability::INPUT_IMAGE,
                default => throw new InvalidArgumentException(\sprintf('The "%s" capability is not supported', $capability)),
            },
            $payload['capabilities'],
        );

        return new Ollama($modelName, $capabilities);
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/api/tags', $this->host));

        $models = $response->toArray();

        return array_merge(...array_map(
            function (array $model): array {
                $retrievedModel = $this->getModel($model['name']);

                return [
                    $retrievedModel->getName() => [
                        'class' => Ollama::class,
                        'capabilities' => $retrievedModel->getCapabilities(),
                    ],
                ];
            },
            $models['models'],
        ));
    }
}
