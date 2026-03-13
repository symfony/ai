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
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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

        if (!isset($payload['capabilities']) || !\is_array($payload['capabilities']) || [] === $payload['capabilities']) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        $capabilities = [];

        foreach ($payload['capabilities'] as $capability) {
            if (!\is_string($capability) || '' === $capability) {
                continue;
            }

            if ('audio' === $capability) {
                $capabilities[] = [
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ];
            }

            if ('embedding' === $capability) {
                $capabilities[] = [
                    Capability::EMBEDDINGS,
                    Capability::OUTPUT_EMBEDDINGS,
                ];
            }

            if ('completion' === $capability) {
                $capabilities[] = [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_IMAGE, // See https://ollama.com/blog/image-generation
                ];
            }

            if ('tools' === $capability) {
                $capabilities[] = [
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_TEXT,
                ];
            }

            if ('thinking' === $capability) {
                $capabilities[] = [
                    Capability::THINKING,
                    Capability::OUTPUT_TEXT,
                ];
            }

            if ('vision' === $capability) {
                $capabilities[] = [
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ];
            }
        }

        return new Ollama($modelName, array_unique(array_merge(...$capabilities), \SORT_REGULAR));
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

        if (!isset($models['models']) || !\is_array($models['models']) || [] === $models['models']) {
            return [];
        }

        return array_merge(...array_map(
            function (mixed $model): array {
                if (!\is_array($model) || !isset($model['name']) || !\is_string($model['name']) || '' === $model['name']) {
                    throw new InvalidArgumentException('Model name is missing or empty.');
                }

                $retrievedModel = $this->getModel($model['name']);

                return [
                    $retrievedModel->getName() => [
                        'class' => Ollama::class,
                        'capabilities' => array_values($retrievedModel->getCapabilities()),
                    ],
                ];
            },
            $models['models'],
        ));
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

            if (\is_array($decoded) && isset($decoded['error']) && \is_string($decoded['error'])) {
                return $decoded['error'];
            }
        } catch (\JsonException) {
            // not JSON, fall through to return raw content
        }

        return $content;
    }
}
