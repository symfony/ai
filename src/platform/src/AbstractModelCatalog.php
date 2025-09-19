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
abstract class AbstractModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: string, capabilities: list<Capability>}>
     */
    protected readonly array $models;

    public function getModel(string $modelName): Model
    {
        if (!isset($this->models[$modelName])) {
            throw ModelNotFoundException::forModelName($modelName);
        }

        $modelConfig = $this->models[$modelName];
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
     * @return array<string, array{class: string, capabilities: list<Capability>}>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    /**
     * @return array{class: string, capabilities: list<Capability>}
     */
    public function getModelConfig(string $modelName): array
    {
        if (!isset($this->models[$modelName])) {
            throw ModelNotFoundException::forModelName($modelName);
        }

        return $this->models[$modelName];
    }

}