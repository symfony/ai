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

        $parsed = $this->parseModelName($modelName);
        $actualModelName = $parsed['name'];
        $catalogKey = $parsed['catalogKey'];
        $options = $parsed['options'];

        if (!isset($this->models[$catalogKey])) {
            throw new ModelNotFoundException(\sprintf('Model "%s" not found in %s.', $actualModelName, static::class));
        }

        $modelConfig = $this->models[$catalogKey];
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
     * Parses a query string into an associative array, preserving periods in keys.
     * Unlike parse_str(), this method does not convert periods to underscores.
     * Supports both dot notation (key.subkey=value) and array notation (key[subkey]=value).
     *
     * @param string $queryString The query string to parse
     *
     * @return array<string, mixed> The parsed parameters as an associative array
     */
    public static function parseQueryString(string $queryString): array
    {
        $result = [];

        if ('' === $queryString) {
            return $result;
        }

        $pairs = explode('&', $queryString);

        foreach ($pairs as $pair) {
            if ('' === $pair) {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            $value = isset($parts[1]) ? urldecode($parts[1]) : '';

            // Handle array notation like key[subkey]=value or key[subkey][nested]=value
            if (str_contains($key, '[')) {
                self::setNestedValue($result, $key, $value);
            } else {
                // Simple key=value pair, preserve as-is (including periods in keys)
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Extracts model name and options from a model name string that may contain query parameters.
     * Also resolves size variants (e.g., "model:23b") to their base model for catalog lookup.
     *
     * @param string $modelName The model name, potentially with query parameters (e.g., "model-name?param=value&other=123")
     *
     * @return array{name: string, catalogKey: string, options: array<string, mixed>} An array containing the model name, catalog lookup key, and parsed options
     */
    protected function parseModelName(string $modelName): array
    {
        $options = [];
        $actualModelName = $modelName;

        if (str_contains($modelName, '?')) {
            [$actualModelName, $queryString] = explode('?', $modelName, 2);

            if ('' === $actualModelName) {
                throw new InvalidArgumentException('Model name cannot be empty.');
            }

            $options = self::parseQueryString($queryString);

            $options = self::convertScalarStrings($options);
        }

        // Determine catalog key: try exact match first, then fall back to base model
        $catalogKey = $actualModelName;
        if (!isset($this->models[$actualModelName]) && str_contains($actualModelName, ':')) {
            $baseModelName = explode(':', $actualModelName, 2)[0];
            if (isset($this->models[$baseModelName])) {
                $catalogKey = $baseModelName;
            }
        }

        return [
            'name' => $actualModelName,
            'catalogKey' => $catalogKey,
            'options' => $options,
        ];
    }

    /**
     * Recursively converts numeric strings to integers or floats in an array.
     *
     * @param array<string, mixed> $data The array to process
     *
     * @return array<string, mixed> The array with numeric and boolean-like strings converted to appropriate numeric/boolean types
     */
    private static function convertScalarStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = self::convertScalarStrings($value);
            } elseif ('true' === $value) {
                $data[$key] = true;
            } elseif ('false' === $value) {
                $data[$key] = false;
            } elseif (is_numeric($value) && \is_string($value)) {
                // Convert to int if it's a whole number, otherwise to float
                $data[$key] = str_contains($value, '.') ? (float) $value : (int) $value;
            }
        }

        return $data;
    }

    /**
     * Sets a nested value in an array using array notation syntax (e.g., "key[subkey][nested]").
     *
     * @param array<string, mixed> $array The array to modify
     * @param string               $key   The key with array notation (e.g., "key[subkey][nested]")
     * @param mixed                $value The value to set
     */
    private static function setNestedValue(array &$array, string $key, mixed $value): void
    {
        // Parse array notation: key[subkey][nested] => ['key', 'subkey', 'nested']
        preg_match_all('/([^\[\]]+)/', $key, $matches);
        $keys = $matches[1];

        $current = &$array;
        foreach ($keys as $i => $k) {
            if ($i === \count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !\is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }
}
