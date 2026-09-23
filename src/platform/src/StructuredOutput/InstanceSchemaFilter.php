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
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface as PropertyAccessExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * Narrows the JSON schema of a class down to the properties a given instance of it is still missing.
 *
 * A property is left to the model when its value is uninitialized, `null`, an empty array or an empty
 * countable collection, and it can be written onto the instance. Every other value, including `''`, `0` and `false`, is taken as
 * given and removed from the schema. A nested object is decided by its own properties: it is removed
 * when nothing is missing on it, and otherwise narrowed the same way to be populated in place.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InstanceSchemaFilter
{
    private readonly PropertyAccessorInterface $propertyAccessor;

    private readonly ClassDiscriminatorResolverInterface $discriminatorResolver;

    public function __construct(
        private readonly PropertyAccessExtractorInterface $accessExtractor = new ReflectionExtractor(),
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?ClassDiscriminatorResolverInterface $discriminatorResolver = null,
    ) {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->discriminatorResolver = $discriminatorResolver ?? new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AttributeLoader()));
    }

    /**
     * @param array<string, mixed> $schema JSON schema of the instance's class
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When the instance is not missing anything
     */
    public function filter(array $schema, object $instance): array
    {
        return $this->narrow($schema, $instance, $schema, []);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $root      Schema that `$ref` pointers resolve against
     * @param array<int, true>     $narrowing Object ids of the instance and its parents, to stop at cycles
     *
     * @return array<string, mixed>
     */
    private function narrow(array $schema, object $instance, array $root, array $narrowing): array
    {
        if (!isset($schema['properties']) || !\is_array($schema['properties'])) {
            return $schema;
        }

        $narrowing[spl_object_id($instance)] = true;

        $properties = [];
        foreach ($schema['properties'] as $name => $propertySchema) {
            // Only what can be written onto the existing instance is worth asking for
            if (!$this->accessExtractor->isWritable($instance::class, $name)) {
                continue;
            }

            $value = $this->readValue($instance, $name);

            // An empty collection object (e.g. a Doctrine Collection) counts as missing, just like an empty array
            if (null === $value || [] === $value || ($value instanceof \Countable && 0 === \count($value))) {
                $properties[$name] = $propertySchema;
                continue;
            }

            // A filled scalar, an object already being narrowed, or one whose schema cannot be narrowed is taken as given
            if (!$this->isPopulatableObject($value) || isset($narrowing[spl_object_id($value)])) {
                continue;
            }

            $objectSchema = $this->resolveObjectSchema($propertySchema, $value, $root);
            if (null === $objectSchema) {
                continue;
            }

            try {
                $objectSchema = $this->narrow($objectSchema, $value, $root, $narrowing);
            } catch (InvalidArgumentException) {
                // Nothing is missing on the nested object, so it is taken as given
                continue;
            }

            // The object already exists and is populated in place, so it must not be answered with null
            if (\is_array($objectSchema['type'] ?? null)) {
                $types = array_values(array_diff($objectSchema['type'], ['null']));
                $objectSchema['type'] = 1 === \count($types) ? $types[0] : $types;
            }

            // Only the branch of the instance's class remains, as the existing object cannot change its class
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
            throw new InvalidArgumentException(\sprintf('The given "%s" instance has no missing properties left to describe.', $instance::class));
        }

        $schema['properties'] = $properties;
        if (isset($schema['required']) && \is_array($schema['required'])) {
            $schema['required'] = array_values(array_filter($schema['required'], static fn (mixed $name): bool => isset($properties[$name])));
        }

        return $schema;
    }

    /**
     * Resolves the object schema describing the given value, following `$ref` pointers and picking the
     * `anyOf`/`oneOf` branch whose discriminator matches the value. Returns null when no such schema is found.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $root
     *
     * @return array<string, mixed>|null
     */
    private function resolveObjectSchema(array $schema, object $value, array $root): ?array
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

            $branch = $this->resolveObjectSchema($branch, $value, $root);
            if (null === $branch) {
                continue;
            }

            $match = $this->matchesDiscriminator($branch, $value);
            if (true === $match) {
                return $branch;
            }

            if (null === $match) {
                $candidates[] = $branch;
            }
        }

        // Without discriminator, only a single object branch (e.g. a nullable object) is unambiguous
        return 1 === \count($candidates) ? $candidates[0] : null;
    }

    /**
     * Compares the single-valued `const`/`enum` properties of a branch with the value's properties, or with
     * the serializer's discriminator type when the property only exists in the value's `DiscriminatorMap`.
     *
     * @param array<string, mixed> $schema
     *
     * @return bool|null Null when the branch has no discriminating property
     */
    private function matchesDiscriminator(array $schema, object $value): ?bool
    {
        $typeProperty = $this->discriminatorResolver->getMappingForMappedObject($value)?->getTypeProperty();

        $discriminated = false;
        foreach ($schema['properties'] as $name => $propertySchema) {
            if (\array_key_exists('const', $propertySchema)) {
                $expected = $propertySchema['const'];
            } elseif (\is_array($propertySchema['enum'] ?? null) && 1 === \count($propertySchema['enum'])) {
                $expected = $propertySchema['enum'][0];
            } else {
                continue;
            }

            $actual = $name === $typeProperty
                ? $this->discriminatorResolver->getTypeForMappedObject($value)
                : $this->readValue($value, $name);
            if ($actual instanceof \BackedEnum) {
                $actual = $actual->value;
            }

            if ($expected !== $actual) {
                return false;
            }

            $discriminated = true;
        }

        return $discriminated ? true : null;
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

    /**
     * Reads the backing property through reflection, regardless of its visibility, so a getter that
     * would throw on an uninitialized property is never invoked. Only a property without backing
     * property falls back to the property accessor. A value that cannot be read is reported as null.
     */
    private function readValue(object $instance, string $name): mixed
    {
        for ($class = new \ReflectionObject($instance); false !== $class; $class = $class->getParentClass()) {
            if (!$class->hasProperty($name)) {
                continue;
            }

            $property = $class->getProperty($name);
            if ($property->isStatic()) {
                break;
            }

            return $property->isInitialized($instance) ? $property->getValue($instance) : null;
        }

        try {
            return $this->propertyAccessor->getValue($instance, $name);
        } catch (PropertyAccessExceptionInterface) {
            return null;
        }
    }

    private function isPopulatableObject(mixed $value): bool
    {
        if (!\is_object($value) || $value instanceof \UnitEnum || $value instanceof \DateTimeInterface) {
            return false;
        }

        return (new \ReflectionObject($value))->isUserDefined();
    }
}
