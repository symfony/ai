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
 * Provides access to provider metadata from the models.dev data.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ProviderRegistry
{
    /**
     * @var array<string, array{name: string, api: string|null}>
     */
    private readonly array $providers;

    public function __construct(
        private ?string $dataPath = null,
    ) {
        $providers = [];
        foreach (DataLoader::load($dataPath) as $providerId => $providerData) {
            $providers[$providerId] = [
                'name' => $providerData['name'] ?? $providerId,
                'api' => $providerData['api'] ?? null,
            ];
        }

        $this->providers = $providers;
    }

    /**
     * @return string|null The API base URL, or null if the provider does not expose one
     */
    public function getApiBaseUrl(string $providerId): ?string
    {
        if (!isset($this->providers[$providerId])) {
            throw new InvalidArgumentException(\sprintf('Provider "%s" not found in registry.', $providerId));
        }

        return null !== ($api = $this->providers[$providerId]['api']) ? rtrim($api, '/') : null;
    }

    public function getProviderName(string $providerId): string
    {
        if (!isset($this->providers[$providerId])) {
            throw new InvalidArgumentException(\sprintf('Provider "%s" not found in registry.', $providerId));
        }

        return $this->providers[$providerId]['name'];
    }

    public function getCatalog(string $providerId): ?ModelCatalog
    {
        if (!isset($this->providers[$providerId])) {
            return null;
        }

        return new ModelCatalog($providerId, $this->dataPath);
    }

    public function has(string $providerId): bool
    {
        return isset($this->providers[$providerId]);
    }

    /**
     * @return list<string>
     */
    public function getProviderIds(): array
    {
        return array_keys($this->providers);
    }
}
