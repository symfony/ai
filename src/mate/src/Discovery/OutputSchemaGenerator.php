<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery;

/**
 * Generates JSON Schema from method return type docblocks.
 *
 * Reads phpstan-return (priority) or return annotations and converts
 * PHPStan array shape syntax into JSON Schema format. Resolves
 * phpstan-import-type references across classes.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class OutputSchemaGenerator
{
    /**
     * Cache of resolved phpstan-type definitions per class.
     *
     * @var array<class-string, array<string, string>>
     */
    private array $typeDefinitionCache = [];

    /**
     * Generate a JSON Schema array from a method's return type docblock.
     *
     * @return array<string, mixed>|null
     */
    public function generate(\ReflectionMethod $method): ?array
    {
        $typeString = $this->extractReturnType($method);

        if (null === $typeString) {
            return null;
        }

        $typeString = $this->resolveImportedTypes($typeString, $method->getDeclaringClass());

        return $this->parseType($typeString);
    }

    private function extractReturnType(\ReflectionMethod $method): ?string
    {
        $docComment = $method->getDocComment();

        if (false === $docComment) {
            return null;
        }

        // Prioritize @phpstan-return over @return
        if (preg_match('/@phpstan-return\s+(.+?)(?:\s*\*\/|\s*\n\s*\*\s*@|\s*\n\s*\*\s*$)/s', $docComment, $matches)) {
            return $this->normalizeTypeString($matches[1]);
        }

        if (preg_match('/@return\s+(.+?)(?:\s*\*\/|\s*\n\s*\*\s*@|\s*\n\s*\*\s*$)/s', $docComment, $matches)) {
            return $this->normalizeTypeString($matches[1]);
        }

        return null;
    }

    private function normalizeTypeString(string $type): string
    {
        // Remove docblock artifacts: leading asterisks, excessive whitespace
        $type = preg_replace('/\n\s*\*\s*/', ' ', $type);
        $type = preg_replace('/\s+/', ' ', $type);

        return trim($type);
    }

    /**
     * Resolve phpstan-import-type references in the type string.
     *
     * @param \ReflectionClass<object> $class
     */
    private function resolveImportedTypes(string $typeString, \ReflectionClass $class): string
    {
        $imports = $this->getImportedTypes($class);

        if ([] === $imports) {
            return $typeString;
        }

        // Replace imported type names with their resolved definitions
        // Sort by length descending to avoid partial replacements
        $names = array_keys($imports);
        usort($names, static fn (string $a, string $b): int => \strlen($b) - \strlen($a));

        foreach ($names as $name) {
            // Only replace when the type name appears as a standalone identifier
            // (not as part of another word)
            $typeString = preg_replace('/\b'.preg_quote($name, '/').'\b/', $imports[$name], $typeString);
        }

        return $typeString;
    }

    /**
     * Get all phpstan-import-type definitions for a class, resolved to their definitions.
     *
     * @param \ReflectionClass<object> $class
     *
     * @return array<string, string>
     */
    private function getImportedTypes(\ReflectionClass $class): array
    {
        $docComment = $class->getDocComment();

        if (false === $docComment) {
            return [];
        }

        $imports = [];

        if (preg_match_all('/@phpstan-import-type\s+(\w+)\s+from\s+([\w\\\\]+)/', $docComment, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $typeName = $match[1];
                $sourceClass = $match[2];

                // Resolve the class name relative to the current namespace
                $resolvedClass = $this->resolveClassName($sourceClass, $class);

                if (null !== $resolvedClass) {
                    $definition = $this->getTypeDefinition($resolvedClass, $typeName);

                    if (null !== $definition) {
                        $imports[$typeName] = $definition;
                    }
                }
            }
        }

        return $imports;
    }

    /**
     * Resolve a class name that may be relative to the declaring class's namespace.
     *
     * @param \ReflectionClass<object> $context
     *
     * @return class-string|null
     */
    private function resolveClassName(string $className, \ReflectionClass $context): ?string
    {
        // Already fully qualified
        if (class_exists($className)) {
            return $className;
        }

        // Try with the same namespace
        $fqcn = $context->getNamespaceName().'\\'.$className;
        if (class_exists($fqcn)) {
            return $fqcn;
        }

        // Try resolving from use statements in the file
        $fileName = $context->getFileName();
        if (false === $fileName) {
            return null;
        }

        $fileContent = file_get_contents($fileName);
        if (false === $fileContent) {
            return null;
        }

        // Find use statements that match the short class name
        if (preg_match('/^use\s+([\w\\\\]+\\\\'.preg_quote($className, '/').')\s*;/m', $fileContent, $match)) {
            $resolved = $match[1];
            if (class_exists($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Get a @phpstan-type definition from a class's docblock.
     */
    private function getTypeDefinition(string $className, string $typeName): ?string
    {
        if (!isset($this->typeDefinitionCache[$className])) {
            $this->typeDefinitionCache[$className] = $this->parseTypeDefinitions($className);
        }

        return $this->typeDefinitionCache[$className][$typeName] ?? null;
    }

    /**
     * Parse all @phpstan-type definitions from a class's docblock.
     *
     * @return array<string, string>
     */
    private function parseTypeDefinitions(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $class = new \ReflectionClass($className);
        $docComment = $class->getDocComment();

        if (false === $docComment) {
            return [];
        }

        $definitions = [];

        // Match @phpstan-type Name = definition (definition may span multiple lines)
        if (preg_match_all('/@phpstan-type\s+(\w+)\s*=?\s*(.+?)(?=\s*\*\s*@|\s*\*\/)/s', $docComment, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $definitions[$match[1]] = $this->normalizeTypeString($match[2]);
            }
        }

        return $definitions;
    }

    /**
     * Parse a PHPStan type string into a JSON Schema array.
     *
     * @return array<string, mixed>|null
     */
    private function parseType(string $type): ?array
    {
        $type = trim($type);

        if ('' === $type || 'mixed' === $type || 'void' === $type || 'never' === $type) {
            return null;
        }

        // Handle array{key: type, ...} object shape
        if (preg_match('/^array\s*\{(.+)\}$/s', $type, $matches)) {
            return $this->parseObjectShape($matches[1]);
        }

        // Handle list<T>
        if (preg_match('/^list\s*<\s*(.+)\s*>$/s', $type, $matches)) {
            $itemSchema = $this->parseType($matches[1]);

            $schema = ['type' => 'array'];
            if (null !== $itemSchema) {
                $schema['items'] = $itemSchema;
            }

            return $schema;
        }

        // Handle array<K, V> (map)
        if (preg_match('/^array\s*<\s*(.+?)\s*,\s*(.+)\s*>$/s', $type, $matches)) {
            $keyType = trim($matches[1]);
            $valueType = trim($matches[2]);

            // array<string, T> -> object with additionalProperties
            if ('string' === $keyType || 'int' === $keyType || 'integer' === $keyType) {
                $valueSchema = $this->parseType($valueType);

                $schema = ['type' => 'object'];
                if (null !== $valueSchema) {
                    $schema['additionalProperties'] = $valueSchema;
                }

                return $schema;
            }

            return ['type' => 'object'];
        }

        // Handle array<T> (simple generic array)
        if (preg_match('/^array\s*<\s*(.+)\s*>$/s', $type, $matches)) {
            $itemSchema = $this->parseType($matches[1]);

            $schema = ['type' => 'array'];
            if (null !== $itemSchema) {
                $schema['items'] = $itemSchema;
            }

            return $schema;
        }

        // Handle T[] syntax
        if (preg_match('/^(.+)\[\]$/', $type, $matches)) {
            $itemSchema = $this->parseType($matches[1]);

            $schema = ['type' => 'array'];
            if (null !== $itemSchema) {
                $schema['items'] = $itemSchema;
            }

            return $schema;
        }

        // Handle nullable ?T
        if (str_starts_with($type, '?')) {
            $innerSchema = $this->parseType(substr($type, 1));

            if (null === $innerSchema) {
                return ['type' => 'null'];
            }

            if (isset($innerSchema['type']) && \is_string($innerSchema['type'])) {
                $innerSchema['type'] = [$innerSchema['type'], 'null'];
            }

            return $innerSchema;
        }

        // Handle union types (T|U)
        if (str_contains($type, '|')) {
            return $this->parseUnionType($type);
        }

        // Handle scalar/simple types
        return $this->parseSimpleType($type);
    }

    /**
     * Parse a union type like "string|null" or "int|string".
     *
     * @return array<string, mixed>|null
     */
    private function parseUnionType(string $type): ?array
    {
        $parts = explode('|', $type);
        $parts = array_map('trim', $parts);

        $hasNull = \in_array('null', $parts, true);
        $nonNullParts = array_values(array_filter($parts, static fn (string $p): bool => 'null' !== $p));

        // Simple nullable: T|null
        if ($hasNull && 1 === \count($nonNullParts)) {
            $innerSchema = $this->parseType($nonNullParts[0]);

            if (null === $innerSchema) {
                return ['type' => 'null'];
            }

            if (isset($innerSchema['type']) && \is_string($innerSchema['type'])) {
                $innerSchema['type'] = [$innerSchema['type'], 'null'];
            }

            return $innerSchema;
        }

        // Multi-type union: use anyOf
        $schemas = [];
        foreach ($parts as $part) {
            $partSchema = $this->parseType($part);
            if (null !== $partSchema) {
                $schemas[] = $partSchema;
            }
        }

        if ([] === $schemas) {
            return null;
        }

        if (1 === \count($schemas)) {
            return $schemas[0];
        }

        return ['anyOf' => $schemas];
    }

    /**
     * Parse an array{key: type, ...} shape into a JSON Schema object.
     *
     * @return array<string, mixed>
     */
    private function parseObjectShape(string $propertiesStr): array
    {
        $properties = [];
        $required = [];

        $entries = $this->splitShapeEntries($propertiesStr);

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ('' === $entry) {
                continue;
            }

            // Match "key?: type" or "key: type"
            if (preg_match('/^(\w+)(\?)?:\s*(.+)$/s', $entry, $matches)) {
                $propName = $matches[1];
                $optional = '?' === $matches[2];
                $propType = trim($matches[3]);

                $propSchema = $this->parseType($propType);

                if (null !== $propSchema) {
                    $properties[$propName] = $propSchema;
                } else {
                    $properties[$propName] = new \stdClass();
                }

                if (!$optional) {
                    $required[] = $propName;
                }
            }
        }

        $schema = ['type' => 'object'];

        if ([] !== $properties) {
            $schema['properties'] = $properties;
        }

        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Split array shape entries respecting nested braces and angle brackets.
     *
     * @return list<string>
     */
    private function splitShapeEntries(string $str): array
    {
        $entries = [];
        $depth = 0;
        $buffer = '';

        for ($i = 0, $len = \strlen($str); $i < $len; ++$i) {
            $char = $str[$i];

            if ('{' === $char || '<' === $char || '(' === $char) {
                ++$depth;
                $buffer .= $char;
            } elseif ('}' === $char || '>' === $char || ')' === $char) {
                --$depth;
                $buffer .= $char;
            } elseif (',' === $char && 0 === $depth) {
                $entries[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        if ('' !== trim($buffer)) {
            $entries[] = $buffer;
        }

        return $entries;
    }

    /**
     * Parse a simple/scalar PHP type into a JSON Schema type.
     *
     * @return array<string, mixed>|null
     */
    private function parseSimpleType(string $type): ?array
    {
        return match (strtolower(ltrim($type, '\\'))) {
            'string', 'class-string', 'non-falsy-string', 'non-empty-string', 'numeric-string', 'literal-string' => ['type' => 'string'],
            'int', 'integer', 'positive-int', 'negative-int', 'non-positive-int', 'non-negative-int' => ['type' => 'integer'],
            'float', 'double', 'number' => ['type' => 'number'],
            'bool', 'boolean', 'true', 'false' => ['type' => 'boolean'],
            'null' => ['type' => 'null'],
            'array' => ['type' => 'array'],
            'object', 'stdclass' => ['type' => 'object'],
            default => null,
        };
    }
}
