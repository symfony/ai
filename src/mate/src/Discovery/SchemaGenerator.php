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
 * Generates a JSON Schema (as a PHP array) for a tool method's parameters.
 *
 * The type of each parameter is derived from its `@param` PHPDoc when specific, falling
 * back to the reflection type. Optionality is derived from default values, descriptions
 * from the `@param` text, and enums/array element types are expanded where possible.
 *
 * @phpstan-import-type ParamTag from DocBlockParser
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SchemaGenerator
{
    public function __construct(
        private DocBlockParser $docBlockParser,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(\ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();
        $paramTags = $this->docBlockParser->getParamTags($docComment);

        $properties = [];
        $required = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $paramTag = $paramTags[$name] ?? null;

            $properties[$name] = $this->buildParameterSchema($parameter, $paramTag);

            if (!$parameter->isOptional()) {
                $required[] = $name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => [] === $properties ? new \stdClass() : $properties,
        ];

        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param ParamTag|null $paramTag
     *
     * @return array<string, mixed>
     */
    private function buildParameterSchema(\ReflectionParameter $parameter, ?array $paramTag): array
    {
        $reflectionType = $parameter->getType();
        $hasDefault = $parameter->isDefaultValueAvailable();
        $defaultValue = $hasDefault ? $parameter->getDefaultValue() : null;

        if ($defaultValue instanceof \BackedEnum) {
            $defaultValue = $defaultValue->value;
        } elseif ($defaultValue instanceof \UnitEnum) {
            $defaultValue = $defaultValue->name;
        }

        $allowsNull = false;
        if (null !== $reflectionType && $reflectionType->allowsNull()) {
            $allowsNull = true;
        } elseif ($hasDefault && null === $defaultValue) {
            $allowsNull = true;
        }

        $typeString = $this->resolveTypeString($parameter, $paramTag);

        if ($parameter->isVariadic()) {
            return $this->buildVariadicSchema($typeString, $paramTag);
        }

        $schema = [];

        $jsonTypes = $this->inferTypes($typeString, $allowsNull);
        if (1 === \count($jsonTypes)) {
            $schema['type'] = $jsonTypes[0];
        } elseif (\count($jsonTypes) > 1) {
            $schema['type'] = $jsonTypes;
        }

        if (null !== ($paramTag['description'] ?? null)) {
            $schema['description'] = $paramTag['description'];
        }

        if ($hasDefault) {
            $schema['default'] = $defaultValue;
        }

        $schema = $this->applyEnumConstraints($schema, $reflectionType);
        $schema = $this->applyArrayConstraints($schema, $typeString, $allowsNull);

        return $this->ensureArrayItems($schema);
    }

    /**
     * @param ParamTag|null $paramTag
     *
     * @return array<string, mixed>
     */
    private function buildVariadicSchema(string $typeString, ?array $paramTag): array
    {
        $schema = ['type' => 'array'];

        if (null !== ($paramTag['description'] ?? null)) {
            $schema['description'] = $paramTag['description'];
        }

        $itemTypes = array_values(array_filter(
            $this->mapPhpTypeToJsonSchemaType($typeString),
            static fn (string $type): bool => 'null' !== $type,
        ));
        if (1 === \count($itemTypes)) {
            $schema['items'] = ['type' => $itemTypes[0]];
        }

        return $this->ensureArrayItems($schema);
    }

    /**
     * @param ParamTag|null $paramTag
     */
    private function resolveTypeString(\ReflectionParameter $parameter, ?array $paramTag): string
    {
        $docType = $paramTag['type'] ?? null;
        $docTypeIsGeneric = null === $docType || \in_array(strtolower($docType), ['mixed', 'unknown', ''], true);

        $reflectionTypeString = null;
        $reflectionType = $parameter->getType();
        if (null !== $reflectionType) {
            $reflectionTypeString = $this->stringifyReflectionType($reflectionType, $parameter->allowsNull());
        }

        if ($docTypeIsGeneric && null !== $reflectionTypeString && 'mixed' !== $reflectionTypeString) {
            return $reflectionTypeString;
        }

        if (null !== $docType && !$docTypeIsGeneric) {
            return $docType;
        }

        return $reflectionTypeString ?? 'mixed';
    }

    private function stringifyReflectionType(\ReflectionType $type, bool $allowsNull): string
    {
        if ($type instanceof \ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $inner) {
                $parts[] = $this->stringifyReflectionType($inner, false);
            }
            $parts = array_filter($parts, static fn (string $part): bool => 'null' !== strtolower($part));
            $typeString = implode('|', array_unique($parts));
        } elseif ($type instanceof \ReflectionNamedType) {
            $typeString = ltrim($type->getName(), '\\');
        } else {
            return 'mixed';
        }

        $typeString = match (strtolower($typeString)) {
            'bool' => 'boolean',
            'int' => 'integer',
            'float', 'double' => 'number',
            default => $typeString,
        };

        if ($allowsNull && 'mixed' !== $typeString && false === stripos($typeString, 'null')) {
            $typeString .= '|null';
        }

        return '' !== $typeString ? $typeString : 'mixed';
    }

    /**
     * @return list<string>
     */
    private function inferTypes(string $typeString, bool $allowsNull): array
    {
        $jsonTypes = $this->mapPhpTypeToJsonSchemaType($typeString);

        if ($allowsNull && 'mixed' !== strtolower($typeString) && !\in_array('null', $jsonTypes, true)) {
            $jsonTypes[] = 'null';
        }

        if (\count($jsonTypes) > 1) {
            $nullIndex = array_search('null', $jsonTypes, true);
            if (false !== $nullIndex) {
                unset($jsonTypes[$nullIndex]);
                sort($jsonTypes);
                array_unshift($jsonTypes, 'null');
            } else {
                sort($jsonTypes);
            }
        }

        return array_values($jsonTypes);
    }

    /**
     * @return list<string>
     */
    private function mapPhpTypeToJsonSchemaType(string $phpType): array
    {
        $normalized = strtolower(trim($phpType));

        if (1 === preg_match('/^array\s*{/i', $normalized)) {
            return ['object'];
        }

        if (str_contains($normalized, '[]') || 1 === preg_match('/^(array|list|iterable|collection)</i', $normalized)) {
            return ['array'];
        }

        if (str_contains($normalized, '|')) {
            $types = [];
            foreach (explode('|', $normalized) as $part) {
                $types = array_merge($types, $this->mapPhpTypeToJsonSchemaType(trim($part)));
            }

            return array_values(array_unique($types));
        }

        return match ($normalized) {
            'string', 'scalar' => ['string'],
            'int', 'integer' => ['integer'],
            'float', 'double', 'number' => ['number'],
            'bool', 'boolean' => ['boolean'],
            'array' => ['array'],
            'object', 'stdclass' => ['object'],
            'null' => ['null'],
            'mixed', 'void', 'never' => [],
            default => ['object'],
        };
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyEnumConstraints(array $schema, ?\ReflectionType $reflectionType): array
    {
        if (!$reflectionType instanceof \ReflectionNamedType || $reflectionType->isBuiltin() || !enum_exists($reflectionType->getName())) {
            return $schema;
        }

        $enumClass = $reflectionType->getName();
        $enumReflection = new \ReflectionEnum($enumClass);
        $backingType = $enumReflection->getBackingType();
        $isNullableUnion = isset($schema['type']) && \is_array($schema['type']) && \in_array('null', $schema['type'], true);

        if ($enumReflection->isBacked() && $backingType instanceof \ReflectionNamedType) {
            /** @var list<int|string> $values */
            $values = array_column($enumClass::cases(), 'value');
            $schema['enum'] = $values;
            $jsonType = match ($backingType->getName()) {
                'int' => 'integer',
                'string' => 'string',
                default => null,
            };
            if (null !== $jsonType) {
                if ($isNullableUnion) {
                    $schema['type'] = [$jsonType, 'null'];
                    $schema['enum'][] = null;
                } else {
                    $schema['type'] = $jsonType;
                }
            }

            return $schema;
        }

        /** @var list<string> $names */
        $names = array_column($enumClass::cases(), 'name');
        $schema['enum'] = $names;
        if ($isNullableUnion) {
            $schema['type'] = ['string', 'null'];
            $schema['enum'][] = null;
        } else {
            $schema['type'] = 'string';
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyArrayConstraints(array $schema, string $typeString, bool $allowsNull): array
    {
        if (!isset($schema['type'])) {
            return $schema;
        }

        if (!\in_array('array', $this->mapPhpTypeToJsonSchemaType($typeString), true)) {
            return $schema;
        }

        $itemType = $this->inferArrayItemsType($typeString);
        if (null !== $itemType) {
            $schema['items'] = ['type' => $itemType];
        }

        if ($allowsNull) {
            $schema['type'] = ['array', 'null'];
            sort($schema['type']);
        } else {
            $schema['type'] = 'array';
        }

        return $schema;
    }

    private function inferArrayItemsType(string $typeString): ?string
    {
        $normalized = trim($typeString);
        $normalized = trim((string) preg_replace('/^null\s*\|\s*|\s*\|\s*null$/i', '', $normalized));

        if (1 === preg_match('/^([\w\\\\]+)\s*\[\]$/', $normalized, $matches)) {
            return $this->mapSimpleType($matches[1]);
        }

        if (1 === preg_match('/^(?:array|list|iterable|collection)\s*<\s*([\w\\\\]+)\s*>$/i', $normalized, $matches)) {
            return $this->mapSimpleType($matches[1]);
        }

        return null;
    }

    private function mapSimpleType(string $type): string
    {
        return match (strtolower($type)) {
            'string' => 'string',
            'int', 'integer' => 'integer',
            'bool', 'boolean' => 'boolean',
            'float', 'double', 'number' => 'number',
            'array' => 'array',
            default => 'object',
        };
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function ensureArrayItems(array $schema): array
    {
        $type = $schema['type'] ?? null;
        $isArray = 'array' === $type || (\is_array($type) && \in_array('array', $type, true));

        if ($isArray && !isset($schema['items'])) {
            $schema['items'] = new \stdClass();
        }

        return $schema;
    }
}
