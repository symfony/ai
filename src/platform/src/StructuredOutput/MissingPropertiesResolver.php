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
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * Selects the properties a given instance is still missing, looking at the instance only.
 *
 * A property is missing when its value is uninitialized, `null`, an empty array or an empty countable
 * collection, and it can be written onto the instance. Every other value, including `''`, `0` and `false`,
 * is taken as given. A nested object is decided by its own properties: it is left out when nothing is
 * missing on it, and otherwise selected with its own missing properties to be populated in place.
 *
 * Property names are PHP property names, which the schema factory and the structured output serializer
 * both use as they are, without name conversion.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MissingPropertiesResolver
{
    private readonly PropertyAccessorInterface $propertyAccessor;

    private readonly ClassDiscriminatorResolverInterface $discriminatorResolver;

    public function __construct(
        private readonly PropertyListExtractorInterface&PropertyAccessExtractorInterface $propertyExtractor = new ReflectionExtractor(),
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?ClassDiscriminatorResolverInterface $discriminatorResolver = null,
    ) {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->discriminatorResolver = $discriminatorResolver ?? new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AttributeLoader()));
    }

    /**
     * @throws InvalidArgumentException When the instance is not missing anything
     */
    public function resolve(object $instance): PropertySelection
    {
        return $this->select($instance, [])
            ?? throw new InvalidArgumentException(\sprintf('The given "%s" instance has no missing properties left to describe.', $instance::class));
    }

    /**
     * @param array<int, true> $resolving Object ids of the instance and its parents, to stop at cycles
     */
    private function select(object $instance, array $resolving): ?PropertySelection
    {
        $resolving[spl_object_id($instance)] = true;

        $properties = [];
        foreach ($this->propertyExtractor->getProperties($instance::class) ?? [] as $name) {
            // Only what can be written onto the existing instance is worth asking for
            if (!$this->propertyExtractor->isWritable($instance::class, $name)) {
                continue;
            }

            $value = $this->readValue($instance, $name);

            // An empty collection object (e.g. a Doctrine Collection) counts as missing, just like an empty array
            if (null === $value || [] === $value || ($value instanceof \Countable && 0 === \count($value))) {
                $properties[$name] = null;
                continue;
            }

            // A filled scalar, or an object already being resolved further up, is taken as given
            if (!$this->isPopulatableObject($value) || isset($resolving[spl_object_id($value)])) {
                continue;
            }

            $selection = $this->select($value, $resolving);
            if (null !== $selection) {
                $properties[$name] = $selection;
            }
        }

        if ([] === $properties) {
            return null;
        }

        $mapping = $this->discriminatorResolver->getMappingForMappedObject($instance);

        return new PropertySelection(
            $properties,
            $mapping?->getTypeProperty(),
            null !== $mapping ? $this->discriminatorResolver->getTypeForMappedObject($instance) : null,
        );
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
