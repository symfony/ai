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
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getModel(string $modelName): Ollama
    {
        $response = $this->httpClient->request('POST', 'api/show', [
            'json' => [
                'model' => $modelName,
            ],
        ]);

        $payload = $response->toArray();

        if ([] === $payload['capabilities']) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        $capabilities = array_merge(...array_map(
            static fn (string $capability): array => match ($capability) {
                'embedding' => [
                    Capability::EMBEDDINGS,
                    Capability::OUTPUT_EMBEDDINGS,
                ],
                'completion' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_IMAGE, // See https://ollama.com/blog/image-generation
                ],
                'tools' => [
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_TEXT,
                ],
                'thinking' => [
                    Capability::THINKING,
                    Capability::OUTPUT_TEXT,
                ],
                'vision' => [
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
                default => throw new InvalidArgumentException(\sprintf('The "%s" capability is not supported', $capability)),
            },
            $payload['capabilities'],
        ));

        return new Ollama($modelName, $capabilities);
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', 'api/tags');

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
