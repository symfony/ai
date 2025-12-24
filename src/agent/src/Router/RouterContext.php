<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Router;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Context for routing decisions, providing access to platform and catalog.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RouterContext
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly ?ModelCatalogInterface $catalog = null,
        private readonly array $metadata = [],
    ) {
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    public function getCatalog(): ?ModelCatalogInterface
    {
        return $this->catalog;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the agent's default model from metadata.
     */
    public function getDefaultModel(): ?string
    {
        return $this->metadata['default_model'] ?? null;
    }

    /**
     * Find models supporting specific capabilities.
     *
     * @return array<string> Model names
     */
    public function findModelsWithCapabilities(Capability ...$capabilities): array
    {
        if (null === $this->catalog) {
            return [];
        }

        $matchingModels = [];
        foreach ($this->catalog->getModels() as $modelName => $modelInfo) {
            $supportsAll = true;
            foreach ($capabilities as $capability) {
                if (!\in_array($capability, $modelInfo['capabilities'], true)) {
                    $supportsAll = false;
                    break;
                }
            }

            if ($supportsAll) {
                $matchingModels[] = $modelName;
            }
        }

        return $matchingModels;
    }
}
