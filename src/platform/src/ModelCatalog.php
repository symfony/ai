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
use Symfony\AI\Platform\Exception\ModelNotFoundException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog implements ModelCatalogInterface
{
    /**
     * @param array<string, array{class: string, platform: string, capabilities: list<Capability>}> $models
     */
    public function __construct(
        private readonly array $models = [],
    ) {
    }

    /**
     * @return array<string, array{class: string, platform: string, capabilities: list<Capability>}>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function getModel(string $modelName): Model
    {
        $modelConfig = $this->getModelConfig($modelName);

        if (null === $modelConfig) {
            throw ModelNotFoundException::forModelName($modelName);
        }

        $modelClass = $modelConfig['class'];
        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        $model = new $modelClass($modelName, $modelConfig['capabilities']);
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend %s.', $modelClass, Model::class));
        }

        return $model;
    }

    /**
     * @return list<string>
     */
    public function getSupportedModels(): array
    {
        return array_keys($this->models);
    }

    /**
     * @return array{class: string, platform: string, capabilities: list<Capability>}|null
     */
    private function getModelConfig(string $name): ?array
    {
        return $this->models[$name] ?? null;
    }
}