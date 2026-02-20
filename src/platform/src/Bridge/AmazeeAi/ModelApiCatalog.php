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
     * @return array<string, array{class: class-string, label: string, capabilities: list<Capability>}>
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
     * @return iterable<string, array{class: class-string<Model>, label: string, capabilities: list<Capability>}>
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

            $label = $this->inferLabel($name, $modelInfo);

            if ('embedding' === $mode) {
                yield $name => [
                    'class' => EmbeddingsModel::class,
                    'label' => $label,
                    'capabilities' => $this->buildEmbeddingCapabilities($info),
                ];
            } else {
                yield $name => [
                    'class' => CompletionsModel::class,
                    'label' => $label,
                    'capabilities' => $this->buildCompletionsCapabilities($info),
                ];
            }
        }
    }

    private function inferLabel(string $name, array $modelInfo): string
    {
        // Generic static mapping for well-known generic names
        $map = [
            'chat' => 'Chat',
            'embeddings' => 'Embeddings',
            'chat_with_image_vision' => 'Chat With Image Vision',
            'chat_with_complex_json' => 'Chat With Complex JSON',
            'chat_with_image' => 'Chat With Image',
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        // Prefer explicit model key if available (e.g. anthropic.claude-3-haiku-20240307-v1:0)
        $raw = $modelInfo['model_info']['key'] ?? $modelInfo['litellm_params']['model'] ?? $name;

        // Try to extract Claude patterns like "claude-3-5-haiku" or
        // anthropic.claude-3-5-sonnet-20240620-v1:0
        if (preg_match('/claude[-._]?([0-9]+)(?:-([0-9]+))?(?:-([a-z]+))?/i', $raw, $m)) {
            $major = $m[1] ?? null;
            $minor = $m[2] ?? null;
            $suffix = $m[3] ?? null;

            $version = $major;
            if (null !== $minor) {
                $version .= '.'.$minor;
            }

            $label = 'Claude '.trim($version);
            if ($suffix) {
                $label .= ' '.ucfirst($suffix);
            }

            return $label;
        }

        // Fall back to humanizing the model name (replace -, _, . with spaces and title-case)
        $human = str_replace(['-', '_', '.'], ' ', $name);

        return ucwords($human);
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
