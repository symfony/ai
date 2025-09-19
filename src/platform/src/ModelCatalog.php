<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class ModelCatalog implements ModelCatalogInterface
{
    /**
     * @param array<string, array{class: string, platform: string, capabilities: list<string>}> $models
     */
    public function __construct(
        private readonly array $models = [],
    ) {
    }

    public function supports(PlatformInterface $platform): bool
    {
        return [] !== $this->getSupportedModels($platform);
    }

    /**
     * @return list<string>
     */
    public function getSupportedModels(PlatformInterface $platform): array
    {
        $supportedModels = [];

        foreach ($this->models as $modelName => $modelConfig) {
            if ($this->isModelSupportedByPlatform($modelName, $platform)) {
                $supportedModels[] = $modelName;
            }
        }

        return $supportedModels;
    }

    public function isModelSupportedByPlatform(string $modelName, PlatformInterface $platform): bool
    {
        $modelConfig = $this->getModel($modelName);
        if (null === $modelConfig || !isset($modelConfig['class'])) {
            return false;
        }

        $modelClass = $modelConfig['class'];
        if (!class_exists($modelClass)) {
            return false;
        }

        try {
            $model = new $modelClass();

            $platform->invoke($model, '');

            return true;
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'No ModelClient registered for model')) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array<string, array{class: string, platform: string, capabilities: list<string>}>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    /**
     * @return array{class: string, platform: string, capabilities: list<string>}|null
     */
    public function getModel(string $name): ?array
    {
        return $this->models[$name] ?? null;
    }

    /**
     * @return list<Capability>
     */
    public function getCapabilities(string $modelName): array
    {
        $model = $this->getModel($modelName);

        if (null === $model) {
            return [];
        }

        return array_map(
            static fn (string $capability): Capability => Capability::from($capability),
            $model['capabilities'] ?? []
        );
    }
}
