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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\ModelDefinition;
use Symfony\AI\Platform\ModelDefinitionsAwareInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class OllamaClient implements ModelClientInterface, ModelDefinitionsAwareInterface
{
    /**
     * @see https://github.com/ollama/ollama/blob/main/types/model/capability.go
     */
    private const CAPABILITY_MAP = [
        'completion' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED],
        'tools' => [Capability::TOOL_CALLING],
        // 'insert' => [],
        'vision' => [Capability::INPUT_IMAGE],
        'embedding' => [Capability::INPUT_MULTIPLE],
        // 'thinking' => [],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $hostUrl,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Ollama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $capabilities = $this->fetchModelDetails($model->getName())['capabilities'] ?? null;

        if (null === $capabilities) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        return match (true) {
            \in_array('completion', $capabilities, true) => $this->doCompletionRequest($payload, $options),
            \in_array('embedding', $capabilities, true) => $this->doEmbeddingsRequest($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('Unsupported model "%s": "%s".', $model::class, $model->getName())),
        };
    }

    /**
     * @return array<string, ModelDefinition>
     */
    public function fetchModelDefinitions(): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/api/tags', $this->hostUrl));
        $models = $response->toArray()['models'] ?? null;

        if (null === $models) {
            throw new InvalidArgumentException('The model information could not be retrieved from the Ollama API. Your Ollama server might be too old. Try upgrade it.');
        }

        $modelDefinitions = array_map($this->buildModelDefinition(...), $models);
        $modelNames = array_map(static fn (ModelDefinition $modelDefinition): string => $modelDefinition->getName(), $modelDefinitions);

        return array_combine($modelNames, $modelDefinitions);
    }

    /**
     * @return array{details: array<string, mixed>, model_info: array<string, mixed>, capabilities: list<string>}
     */
    private function fetchModelDetails(string $model): array
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/api/show', $this->hostUrl), [
            'json' => [
                'model' => $model,
            ],
        ]);

        return $response->toArray();
    }

    /**
     * @param array{name: string, model: string, modified_at: string, size: int} $model
     *
     * @see https://github.com/ollama/ollama/blob/main/docs/api.md#list-local-models
     */
    private function buildModelDefinition(array $model): ModelDefinition
    {
        $modelInformation = $this->fetchModelDetails($model['model']);

        $capabilities = [];
        foreach ($modelInformation['capabilities'] ?? [] as $capability) {
            $capabilities = [...$capabilities, ...(self::CAPABILITY_MAP[$capability] ?? [])];
        }

        $meta = [
            'family' => $modelInformation['details']['family'] ?? null,
            'modified_at' => $model['modified_at'] ?? null,
            'parameter_size' => $modelInformation['model_info']['general.parameter_count'] ?? null,
        ];

        return new ModelDefinition($model['name'], $capabilities, $meta);
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doCompletionRequest(array|string $payload, array $options = []): RawHttpResult
    {
        // Revert Ollama's default streaming behavior
        $options['stream'] ??= false;

        if (\array_key_exists('response_format', $options) && \array_key_exists('json_schema', $options['response_format'])) {
            $options['format'] = $options['response_format']['json_schema']['schema'];
            unset($options['response_format']);
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/api/chat', $this->hostUrl), [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, $payload),
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doEmbeddingsRequest(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/api/embed', $this->hostUrl), [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }
}
