<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Resolves a model specification string to a (provider, model ID) pair.
 *
 * Accepted input formats (resolved in that order):
 *
 *   * "provider::model": Explicit form like "anthropic::claude-opus-4-5"
 *
 *   * "provider": Provider-only, returns the first non-deprecated model listed
 *     in that provider's catalog.
 *
 *   * "model": Model ID, scans all provider catalogs to find the owning
 *     provider, checking canonical providers first over aggregator/proxy
 *     providers being preferred.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ModelResolver
{
    /**
     * @var list<string>
     */
    private const CANONICAL_PROVIDERS = [
        'anthropic', 'openai', 'google', 'mistral', 'xai',
        'deepseek', 'groq', 'cerebras', 'cohere',
    ];

    private readonly ProviderRegistry $registry;

    public function __construct(
        ?ProviderRegistry $registry = null,
        ?string $dataPath = null,
    ) {
        $this->registry = $registry ?? new ProviderRegistry($dataPath);
    }

    /**
     * Resolves a model specification to a (provider, modelId) pair.
     *
     * @return array{provider: string, model_id: string}
     *
     * @throws InvalidArgumentException when the provider or model cannot be determined
     */
    public function resolve(string $modelSpec): array
    {
        // "provider::model"
        if (str_contains($modelSpec, '::')) {
            [$provider, $modelId] = explode('::', $modelSpec, 2);

            if ('' === $provider) {
                throw new InvalidArgumentException(\sprintf('Invalid model specification "%s": provider part is empty.', $modelSpec));
            }
            if ('' === $modelId) {
                throw new InvalidArgumentException(\sprintf('Invalid model specification "%s": model ID part is empty.', $modelSpec));
            }

            return ['provider' => $provider, 'model_id' => $modelId];
        }

        // "provider"
        if ($this->registry->has($modelSpec)) {
            return ['provider' => $modelSpec, 'model_id' => $this->resolveFirstModel($modelSpec)];
        }

        // "model-id"
        return ['provider' => $this->findProviderForModel($modelSpec), 'model_id' => $modelSpec];
    }

    /**
     * Returns the first non-deprecated model ID from a provider's catalog.
     *
     * @throws InvalidArgumentException when no models are found for the provider
     */
    private function resolveFirstModel(string $provider): string
    {
        if (null === $catalog = $this->registry->getCatalog($provider)) {
            throw new InvalidArgumentException(\sprintf('No model catalog found for provider "%s"; use "provider::model" format.', $provider));
        }

        if ([] === $models = array_keys($catalog->getModels())) {
            throw new InvalidArgumentException(\sprintf('No models found for provider "%s"; use "provider::model" format.', $provider));
        }

        return $models[0];
    }

    /**
     * Scans provider catalogs to find which provider owns a given model ID.
     *
     * Canonical providers are checked first; all others follow in registry order.
     *
     * @throws InvalidArgumentException when no provider lists the model
     */
    private function findProviderForModel(string $modelId): string
    {
        // Check canonical providers first for deterministic resolution
        foreach (self::CANONICAL_PROVIDERS as $providerId) {
            if (isset($this->registry->getCatalog($providerId)?->getModels()[$modelId])) {
                return $providerId;
            }
        }

        // Fall back to full registry scan
        foreach ($this->registry->getProviderIds() as $providerId) {
            if (\in_array($providerId, self::CANONICAL_PROVIDERS, true)) {
                continue; // already checked
            }

            if (isset($this->registry->getCatalog($providerId)?->getModels()[$modelId])) {
                return $providerId;
            }
        }

        throw new InvalidArgumentException(\sprintf('Cannot determine provider for model "%s"; Use "provider::model" format to specify it explicitly.', $modelId));
    }
}
