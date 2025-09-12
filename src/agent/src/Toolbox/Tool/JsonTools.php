<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('json_parse', 'Parse JSON string and return decoded data')]
#[AsTool('json_encode', 'Encode PHP data structure to JSON string', method: 'encode')]
#[AsTool('json_validate', 'Validate if a string is valid JSON', method: 'validate')]
#[AsTool('json_get_keys', 'Get keys from a JSON object at a given path', method: 'getKeys')]
#[AsTool('json_get_value', 'Get value from JSON at a given path', method: 'getValue')]
#[AsTool('json_merge', 'Merge multiple JSON objects', method: 'merge')]
#[AsTool('json_search', 'Search for a value in JSON data', method: 'search')]
final readonly class JsonTools
{
    public function __construct(
        private int $maxValueLength = 200,
    ) {
    }

    /**
     * Parse JSON string and return decoded data.
     *
     * @param string $jsonString The JSON string to parse
     *
     * @return array<string, mixed>|null
     */
    public function __invoke(string $jsonString): ?array
    {
        try {
            $decoded = json_decode($jsonString, true, 512, \JSON_THROW_ON_ERROR);

            return \is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Encode PHP data structure to JSON string.
     *
     * @param mixed $data  The data to encode
     * @param int   $flags JSON encoding flags
     */
    public function encode(mixed $data, int $flags = \JSON_PRETTY_PRINT): string
    {
        try {
            return json_encode($data, $flags | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return 'Error encoding JSON: '.$e->getMessage();
        }
    }

    /**
     * Validate if a string is valid JSON.
     *
     * @param string $jsonString The JSON string to validate
     *
     * @return array{valid: bool, error?: string}
     */
    public function validate(string $jsonString): array
    {
        try {
            json_decode($jsonString, true, 512, \JSON_THROW_ON_ERROR);

            return ['valid' => true];
        } catch (\JsonException $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get keys from a JSON object at a given path.
     *
     * @param string $jsonString The JSON string
     * @param string $path       The path to the object (e.g., "data.users")
     *
     * @return array<int, string>|string
     */
    public function getKeys(string $jsonString, string $path = ''): array|string
    {
        try {
            $data = json_decode($jsonString, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                return 'Root data is not an object';
            }

            $target = $this->getValueAtPath($data, $path);

            if (!\is_array($target)) {
                return 'Value at path is not an object, get the value directly';
            }

            return array_keys($target);
        } catch (\JsonException $e) {
            return 'JSON parse error: '.$e->getMessage();
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Get value from JSON at a given path.
     *
     * @param string $path The path to the value (e.g., "data.users[0].name")
     */
    public function getValue(string $jsonString, string $path = ''): mixed
    {
        try {
            $data = json_decode($jsonString, true, 512, \JSON_THROW_ON_ERROR);

            if (empty($path)) {
                return $data;
            }

            $value = $this->getValueAtPath($data, $path);

            if (\is_array($value) && \count($value) > 10) {
                return 'Value is a large array/object, should explore its keys directly';
            }

            $stringValue = json_encode($value, \JSON_PRETTY_PRINT);

            if (\strlen($stringValue) > $this->maxValueLength) {
                return substr($stringValue, 0, $this->maxValueLength).'...';
            }

            return $value;
        } catch (\JsonException $e) {
            return 'JSON parse error: '.$e->getMessage();
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Merge multiple JSON objects.
     *
     * @param string ...$jsonStrings JSON strings to merge
     */
    public function merge(string ...$jsonStrings): string
    {
        try {
            $result = [];

            foreach ($jsonStrings as $jsonString) {
                $data = json_decode($jsonString, true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($data)) {
                    $result = array_merge_recursive($result, $data);
                }
            }

            return json_encode($result, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return 'Error merging JSON: '.$e->getMessage();
        }
    }

    /**
     * Search for a value in JSON data.
     *
     * @param string $jsonString    The JSON string to search in
     * @param string $searchTerm    The term to search for
     * @param bool   $caseSensitive Whether the search should be case sensitive
     *
     * @return array<int, array{path: string, value: mixed}>
     */
    public function search(string $jsonString, string $searchTerm, bool $caseSensitive = false): array
    {
        try {
            $data = json_decode($jsonString, true, 512, \JSON_THROW_ON_ERROR);
            $results = [];

            $this->searchRecursive($data, $searchTerm, '', $results, $caseSensitive);

            return $results;
        } catch (\JsonException $e) {
            return [['path' => 'error', 'value' => 'JSON parse error: '.$e->getMessage()]];
        }
    }

    /**
     * Get value at a specific path in the data structure.
     */
    private function getValueAtPath(array $data, string $path): mixed
    {
        if (empty($path)) {
            return $data;
        }

        $keys = $this->parsePath($path);
        $current = $data;

        foreach ($keys as $key) {
            if (\is_array($current) && \array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                throw new \InvalidArgumentException("Path '{$path}' not found.");
            }
        }

        return $current;
    }

    /**
     * Parse a path string into an array of keys.
     */
    private function parsePath(string $path): array
    {
        // Handle array notation like "users[0].name"
        $pattern = '/\[([^\]]+)\]/';
        $path = preg_replace($pattern, '.$1', $path);

        $keys = explode('.', $path);
        $result = [];

        foreach ($keys as $key) {
            $key = trim($key);
            if ('' === $key) {
                continue;
            }

            // Convert numeric strings to integers for array access
            if (is_numeric($key)) {
                $result[] = (int) $key;
            } else {
                $result[] = $key;
            }
        }

        return $result;
    }

    /**
     * Recursively search for a term in the data structure.
     */
    private function searchRecursive(mixed $data, string $searchTerm, string $currentPath, array &$results, bool $caseSensitive): void
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $newPath = '' === $currentPath ? (string) $key : $currentPath.'.'.$key;
                $this->searchRecursive($value, $searchTerm, $newPath, $results, $caseSensitive);
            }
        } elseif (\is_string($data)) {
            $dataToSearch = $caseSensitive ? $data : strtolower($data);
            $searchToFind = $caseSensitive ? $searchTerm : strtolower($searchTerm);

            if (str_contains($dataToSearch, $searchToFind)) {
                $results[] = [
                    'path' => $currentPath,
                    'value' => $data,
                ];
            }
        }
    }
}
