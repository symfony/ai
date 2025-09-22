<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelCatalog;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class AbstractModelCatalog implements ModelCatalogInterface
{
    /**
     * @var array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    protected array $models;

    public function getModel(string $modelName): Model
    {
        if ('' === $modelName) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $parsed = self::parseModelName($modelName);
        $actualModelName = $parsed['name'];
        $options = $parsed['options'];

        if (!isset($this->models[$actualModelName])) {
            throw new ModelNotFoundException(\sprintf('Model "%s" not found.', $actualModelName));
        }

        $modelConfig = $this->models[$actualModelName];
        $modelClass = $modelConfig['class'];

        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        $model = new $modelClass($actualModelName, $modelConfig['capabilities'], $options);
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend "%s".', $modelClass, Model::class));
        }

        return $model;
    }

    /**
     * @return array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    /**
     * Extracts model name and options from a model name string that may contain query parameters.
     *
     * @param string $modelName The model name, potentially with query parameters (e.g., "model-name?param=value&other=123")
     *
     * @return array{name: string, options: array<string, mixed>} An array containing the model name and parsed options
     */
    protected static function parseModelName(string $modelName): array
    {
        $options = [];
        $actualModelName = $modelName;

        if (str_contains($modelName, '?')) {
            [$actualModelName, $queryString] = explode('?', $modelName, 2);

            if ('' === $actualModelName) {
                throw new InvalidArgumentException('Model name cannot be empty.');
            }

            parse_str($queryString, $options);
        }

        return [
            'name' => $actualModelName,
            'options' => $options,
        ];
    }
}
