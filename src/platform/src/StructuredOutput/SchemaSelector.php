<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Narrows a JSON schema down to a property selection, and tells the selection a schema asks for, looking at the schema only.
 *
 * A nested selection is applied to the object schema behind the property, following `$ref` pointers and
 * picking the `anyOf`/`oneOf` branch that matches the selection's discriminator. Such an object already
 * exists, so it loses its `null` type. A nested object that cannot be narrowed is kept in full, as the
 * selection still limits what is written back onto the instance.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SchemaSelector
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When no selected property is part of the schema
     */
    public function select(array $schema, PropertySelection $selection): array
    {
        return $this->narrow($schema, $selection, $schema);
    }

    /**
     * The properties the schema asks for, down to nested objects and collection items. The branches of an
     * `anyOf`/`oneOf` are merged, and a reference back to a schema being read is selected in full.
     *
     * @param array<string, mixed> $schema
     */
    public function selectionOf(array $schema): PropertySelection
    {
        return $this->describedSelection($schema, $schema, []) ?? new PropertySelection([]);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $root   Schema that `$ref` pointers resolve against
     *
     * @return array<string, mixed>
     */
    private function narrow(array $schema, PropertySelection $selection, array $root): array
    {
        if (!isset($schema['properties']) || !\is_array($schema['properties'])) {
            return $schema;
        }

        $selected = $selection->getProperties();

        $properties = [];
        foreach ($schema['properties'] as $name => $propertySchema) {
            if (!\array_key_exists($name, $selected)) {
                continue;
            }

            $nested = $selected[$name];
            if (null === $nested || !\is_array($propertySchema)) {
                $properties[$name] = $propertySchema;
                continue;
            }

            $objectSchema = $this->resolveObjectSchema($propertySchema, $nested, $root);
            if (null === $objectSchema) {
                $properties[$name] = $propertySchema;
                continue;
            }

            try {
                $objectSchema = $this->narrow($objectSchema, $nested, $root);
            } catch (InvalidArgumentException) {
                continue;
            }

            if (\is_array($objectSchema['type'] ?? null)) {
                $types = array_values(array_diff($objectSchema['type'], ['null']));
                $objectSchema['type'] = 1 === \count($types) ? $types[0] : $types;
            }

            // Only the branch of the existing object remains, as populating it cannot change its class
            foreach (['anyOf', 'oneOf'] as $keyword) {
                if (isset($propertySchema[$keyword])) {
                    $propertySchema[$keyword] = [$objectSchema];
                    $objectSchema = $propertySchema;
                    break;
                }
            }

            $properties[$name] = $objectSchema;
        }

        if ([] === $properties) {
            throw new InvalidArgumentException('None of the selected properties is part of the schema.');
        }

        $schema['properties'] = $properties;
        if (isset($schema['required']) && \is_array($schema['required'])) {
            $schema['required'] = array_values(array_filter($schema['required'], static fn (mixed $name): bool => isset($properties[$name])));
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $root
     *
     * @return array<string, mixed>|null
     */
    private function resolveObjectSchema(array $schema, PropertySelection $selection, array $root): ?array
    {
        if (isset($schema['$ref']) && \is_string($schema['$ref'])) {
            $resolved = $this->resolveReference($schema['$ref'], $root);
            if (null === $resolved) {
                return null;
            }

            // Siblings of the reference, like a description, take precedence over the referenced schema
            unset($schema['$ref']);
            $schema += $resolved;
        }

        if (isset($schema['properties'])) {
            return $schema;
        }

        $branches = $schema['anyOf'] ?? $schema['oneOf'] ?? null;
        if (!\is_array($branches)) {
            return null;
        }

        $candidates = [];
        foreach ($branches as $branch) {
            if (!\is_array($branch)) {
                continue;
            }

            $branch = $this->resolveObjectSchema($branch, $selection, $root);
            if (null === $branch) {
                continue;
            }

            if (null === $selection->getDiscriminatorProperty()) {
                $candidates[] = $branch;
                continue;
            }

            if ($this->matchesDiscriminator($branch, $selection)) {
                return $branch;
            }
        }

        // Without discriminator, only a single object branch (e.g. a nullable object) is unambiguous
        return 1 === \count($candidates) ? $candidates[0] : null;
    }

    /**
     * @param array<string, mixed> $branch
     */
    private function matchesDiscriminator(array $branch, PropertySelection $selection): bool
    {
        $property = $branch['properties'][$selection->getDiscriminatorProperty()] ?? null;
        if (!\is_array($property)) {
            return false;
        }

        if (\array_key_exists('const', $property)) {
            return $property['const'] === $selection->getDiscriminatorValue();
        }

        return ($property['enum'] ?? null) === [$selection->getDiscriminatorValue()];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $root
     * @param array<string, true>  $reading References being read further up, to stop at recursion
     *
     * @return PropertySelection|null Null when the schema is not an object, or selects it in full
     */
    private function describedSelection(array $schema, array $root, array $reading): ?PropertySelection
    {
        if (isset($schema['$ref']) && \is_string($schema['$ref'])) {
            $resolved = $this->resolveReference($schema['$ref'], $root);
            if (null === $resolved || isset($reading[$schema['$ref']])) {
                return null;
            }

            $reading[$schema['$ref']] = true;
            $schema = $resolved;
        }

        if (isset($schema['properties']) && \is_array($schema['properties'])) {
            $properties = [];
            foreach ($schema['properties'] as $name => $propertySchema) {
                $properties[$name] = \is_array($propertySchema) ? $this->describedSelection($propertySchema, $root, $reading) : null;
            }

            return new PropertySelection($properties);
        }

        if (isset($schema['items']) && \is_array($schema['items'])) {
            return $this->describedSelection($schema['items'], $root, $reading);
        }

        $branches = $schema['anyOf'] ?? $schema['oneOf'] ?? null;
        if (!\is_array($branches)) {
            return null;
        }

        $merged = null;
        foreach ($branches as $branch) {
            // A scalar branch, like the null of a nullable object, adds no properties
            if (!\is_array($branch) || null === $selection = $this->describedSelection($branch, $root, $reading)) {
                continue;
            }

            $merged = null === $merged ? $selection : $this->merge($merged, $selection);
        }

        return $merged;
    }

    private function merge(PropertySelection $left, PropertySelection $right): PropertySelection
    {
        $properties = $left->getProperties();
        foreach ($right->getProperties() as $name => $selection) {
            if (!\array_key_exists($name, $properties)) {
                $properties[$name] = $selection;
                continue;
            }

            // Selected in full on either side stays in full
            $properties[$name] = null === $properties[$name] || null === $selection ? null : $this->merge($properties[$name], $selection);
        }

        return new PropertySelection($properties);
    }

    /**
     * @param array<string, mixed> $root
     *
     * @return array<string, mixed>|null
     */
    private function resolveReference(string $reference, array $root): ?array
    {
        if (!str_starts_with($reference, '#/')) {
            return null;
        }

        $schema = $root;
        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (!\is_array($schema) || !isset($schema[$segment])) {
                return null;
            }

            $schema = $schema[$segment];
        }

        return \is_array($schema) ? $schema : null;
    }
}
