<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Discovers Fireworks models through the gateway control-plane API and merges them with a
 * minimal static overlay for the capabilities (image generation, reranking) the gateway does
 * not flag.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    public const DEFAULT_GATEWAY_ENDPOINT = 'https://api.fireworks.ai';

    private bool $modelsAreLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $accountId = 'fireworks',
        private readonly ?string $gatewayEndpoint = null,
    ) {
        $this->models = $this->overlayModels();
    }

    public function getModel(string $modelName): Model
    {
        $this->preloadRemoteModels();

        return parent::getModel($modelName);
    }

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

        // Remote models are loaded first so the static overlay (image/rerank) always wins on collisions.
        $this->models = [...$this->fetchRemoteModels(), ...$this->models];
        ksort($this->models);
        $this->modelsAreLoaded = true;
    }

    /**
     * @return array<string, array{class: class-string<Model>, capabilities: list<Capability>}>
     */
    private function overlayModels(): array
    {
        return [
            'accounts/fireworks/models/flux-1-schnell-fp8' => [
                'class' => Fireworks::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::TEXT_TO_IMAGE,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'accounts/fireworks/models/qwen3-reranker-8b' => [
                'class' => Fireworks::class,
                'capabilities' => [
                    Capability::RERANKING,
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, array{class: class-string<Model>, capabilities: list<Capability>}>
     */
    private function fetchRemoteModels(): iterable
    {
        $endpoint = $this->gatewayEndpoint ?? self::DEFAULT_GATEWAY_ENDPOINT;
        $pageToken = null;

        do {
            $response = $this->httpClient->request('GET', \sprintf('%s/v1/accounts/%s/models', $endpoint, $this->accountId), [
                'auth_bearer' => $this->apiKey,
                'query' => null === $pageToken ? ['pageSize' => 200] : ['pageSize' => 200, 'pageToken' => $pageToken],
            ]);

            $payload = $response->toArray();
            $pageToken = $payload['nextPageToken'] ?? null;

            foreach ($payload['models'] ?? [] as $model) {
                yield $model['name'] => [
                    'class' => Fireworks::class,
                    'capabilities' => $this->detectCapabilities($model),
                ];
            }
        } while (null !== $pageToken && '' !== $pageToken);
    }

    /**
     * @param array<string, mixed> $model
     *
     * @return list<Capability>
     */
    private function detectCapabilities(array $model): array
    {
        if ('EMBEDDING_MODEL' === ($model['kind'] ?? null)) {
            return [Capability::INPUT_TEXT, Capability::EMBEDDINGS];
        }

        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ];

        if ($model['supportsTools'] ?? false) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        if ($model['supportsImageInput'] ?? false) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        return $capabilities;
    }
}
