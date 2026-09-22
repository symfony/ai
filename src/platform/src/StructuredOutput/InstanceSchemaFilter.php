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

    public function __construct(
        private readonly PropertyAccessExtractorInterface $accessExtractor = new ReflectionExtractor(),
        ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
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
        if (!isset($schema['properties']) || !\is_array($schema['properties'])) {
            return $schema;
        }

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

            // A filled scalar, or an object whose schema cannot be narrowed (e.g. a union), is taken as given
            if (!$this->isPopulatableObject($value) || !isset($propertySchema['properties'])) {
                continue;
            }

            try {
                $propertySchema = $this->filter($propertySchema, $value);
            } catch (InvalidArgumentException) {
                // Nothing is missing on the nested object, so it is taken as given
                continue;
            }

            // The object already exists and is populated in place, so it must not be answered with null
            if (\is_array($propertySchema['type'] ?? null)) {
                $types = array_values(array_diff($propertySchema['type'], ['null']));
                $propertySchema['type'] = 1 === \count($types) ? $types[0] : $types;
            }

            $properties[$name] = $propertySchema;
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
