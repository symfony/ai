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

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class AbstractModelCatalog implements ModelCatalogInterface
{
    /**
     * @param array<string, array{class: string, platform: string, capabilities: list<string>}> $models
     */
    public function __construct(
        protected readonly array $models = [],
    ) {
    }

    public function get(string $modelName): Model
    {
        return $this->getModel($modelName);
    }

    /**
     * @return array<string, array{class: string, platform: string, capabilities: list<string>}>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function getModel(string $modelName): Model
    {
        $modelConfig = $this->getModelConfig($modelName);

        if (null === $modelConfig) {
            throw new InvalidArgumentException(\sprintf('Model "%s" not found in catalog.', $modelName));
        }

        $modelClass = $modelConfig['class'];
        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        $model = new $modelClass();
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend %s.', $modelClass, Model::class));
        }

        return $model;
    }

    /**
     * @return list<Capability>
     */
    public function getCapabilities(string $modelName): array
    {
        $modelConfig = $this->getModelConfig($modelName);

        if (null === $modelConfig) {
            return [];
        }

        return array_map(
            static fn (string $capability): Capability => Capability::from($capability),
            $modelConfig['capabilities'] ?? []
        );
    }

    /**
     * @return list<string>
     */
    public function getSupportedModels(PlatformInterface $platform): array
    {
        if (!$this->supports($platform)) {
            return [];
        }

        return array_keys($this->models);
    }

    /**
     * @return array{class: string, platform: string, capabilities: list<string>}|null
     */
    private function getModelConfig(string $name): ?array
    {
        return $this->models[$name] ?? null;
    }
}
