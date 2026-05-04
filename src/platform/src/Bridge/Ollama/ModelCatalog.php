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
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @var array<string, Ollama>
     */
    private array $modelCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        if (isset($this->modelCache[$modelName])) {
            return $this->modelCache[$modelName];
        }

        $response = $this->httpClient->request('POST', 'api/show', [
            'json' => [
                'model' => $modelName,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot connect to the Ollama API: "%s".', $e->getMessage()), previous: $e);
        }

        if (200 !== $statusCode) {
            $errorMessage = $this->extractErrorMessage($response);

            if (404 === $statusCode) {
                throw new ModelNotFoundException(null !== $errorMessage ? \sprintf('Model "%s" not found: "%s".', $modelName, $errorMessage) : \sprintf('Model "%s" not found.', $modelName));
            }

            throw new RuntimeException(null !== $errorMessage ? \sprintf('Cannot load model information from the Ollama API (Status code: %d): "%s".', $statusCode, $errorMessage) : \sprintf('Cannot load model information from the Ollama API (Status code: %d).', $statusCode));
        }

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
                'audio' => Capability::INPUT_AUDIO,
                default => throw new InvalidArgumentException(\sprintf('The "%s" capability is not supported', $capability)),
            },
            $payload['capabilities'],
        );

        if (!\in_array(Capability::EMBEDDINGS, $capabilities, true)) {
            $capabilities[] = Capability::OUTPUT_STRUCTURED;
        }

        $endpoints = $this->endpointsForModel(['class' => Ollama::class, 'capabilities' => $capabilities]);

        return $this->modelCache[$modelName] = new Ollama($modelName, $capabilities, [], $endpoints);
    }

    public function getModels(): array
    {
        $response = $this->httpClient->request('GET', 'api/tags');

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot connect to the Ollama API: "%s".', $e->getMessage()), previous: $e);
        }

        if (200 !== $statusCode) {
            $errorMessage = $this->extractErrorMessage($response);

            throw new RuntimeException(null !== $errorMessage ? \sprintf('Cannot retrieve models from the Ollama API (Status code: %d): "%s".', $statusCode, $errorMessage) : \sprintf('Cannot retrieve models from the Ollama API (Status code: %d).', $statusCode));
        }

        $models = $response->toArray();

        if ([] === $models['models']) {
            return [];
        }

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

    protected function endpointsForModel(array $modelConfig): array
    {
        $endpoints = [];
        $caps = $modelConfig['capabilities'];
        if (\in_array(Capability::INPUT_MESSAGES, $caps, true)) {
            $endpoints[] = new Endpoint(ChatClient::ENDPOINT);
        }
        if (\in_array(Capability::EMBEDDINGS, $caps, true)) {
            $endpoints[] = new Endpoint(EmbedClient::ENDPOINT);
        }

        return $endpoints;
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface) {
            return null;
        }

        if ('' === $content) {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($decoded) && isset($decoded['error'])) {
                return $decoded['error'];
            }
        } catch (\JsonException) {
            // not JSON, fall through to return raw content
        }

        return $content;
    }
}
