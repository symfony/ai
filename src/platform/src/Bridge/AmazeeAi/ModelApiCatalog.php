<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AmazeeAi;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Model catalog that discovers available models from the amazee.ai
 * LiteLLM /model/info endpoint.
 *
 * Maps each model to CompletionsModel or EmbeddingsModel based on the
 * mode field, so the Generic platform's ModelClients can route requests
 * correctly.
 */
final class ModelApiCatalog extends AbstractModelCatalog
{
    private bool $modelsAreLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
    ) {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        return parent::getModel($modelName);
    }

    /**
     * @return array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    public function getModels(): array
    {
        $this->preloadRemoteModels();

        return parent::getModels();
    }

    private function preloadRemoteModels(): void
    {
        if ($this->modelsAreLoaded) {
            return;
        }

        $this->modelsAreLoaded = true;
        $this->models = [...$this->models, ...$this->fetchRemoteModels()];
    }

    /**
     * @return iterable<string, array{class: class-string<Model>, capabilities: list<Capability>}>
     */
    private function fetchRemoteModels(): iterable
    {
        $response = $this->httpClient->request('GET', $this->baseUrl.'/model/info', [
            'headers' => array_filter([
                'Authorization' => $this->apiKey ? 'Bearer '.$this->apiKey : null,
            ]),
        ]);

        foreach ($response->toArray()['data'] ?? [] as $modelInfo) {
            $name = $modelInfo['model_name'] ?? null;
            if (null === $name) {
                continue;
            }

            $info = $modelInfo['model_info'] ?? [];
            $mode = $info['mode'] ?? null;

            if ('embedding' === $mode) {
                yield $name => [
                    'class' => EmbeddingsModel::class,
                    'capabilities' => $this->buildEmbeddingCapabilities($info),
                ];
            } else {
                yield $name => [
                    'class' => CompletionsModel::class,
                    'capabilities' => $this->buildCompletionsCapabilities($info),
                ];
            }
        }
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return list<Capability>
     */
    private function buildEmbeddingCapabilities(array $info): array
    {
        $capabilities = [Capability::EMBEDDINGS, Capability::INPUT_TEXT];

        if ($info['supports_multiple_inputs'] ?? true) {
            $capabilities[] = Capability::INPUT_MULTIPLE;
        }

        return $capabilities;
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return list<Capability>
     */
    private function buildCompletionsCapabilities(array $info): array
    {
        $capabilities = [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING];

        if ($info['supports_image_input'] ?? false) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }
        if ($info['supports_audio_input'] ?? false) {
            $capabilities[] = Capability::INPUT_AUDIO;
        }
        if ($info['supports_tool_calling'] ?? $info['supports_function_calling'] ?? false) {
            $capabilities[] = Capability::TOOL_CALLING;
        }
        if ($info['supports_response_schema'] ?? false) {
            $capabilities[] = Capability::OUTPUT_STRUCTURED;
        }

        return $capabilities;
    }
}
