<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Selector;

use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

/**
 * Opens only the properties an instance is still missing.
 *
 * A property is missing when it is uninitialized, `null` or an empty array; every other value, including `''`,
 * `0` and `false`, is taken as given. So is a property that cannot be written onto the instance, since populating
 * an existing instance never calls its constructor. A nested object is decided by its own properties, through the
 * selector returned for it.
 *
 * @author Marco van Angeren <marco@jouwweb.nl>
 */
final class MissingPropertiesSelector implements PropertySelectorInterface
{
    public function __construct(
        private readonly object $instance,
        private readonly PropertyAccessExtractorInterface&PropertyReadInfoExtractorInterface $propertyInfo = new ReflectionExtractor(),
    ) {
    }

    public function isOpen(ObjectSubject $object, PropertySubject $property): bool
    {
        $class = $object->getReflector();
        // Another class than the instance's, e.g. one of the other branches of an `anyOf`, is described in full
        if (!$class instanceof \ReflectionClass || !$this->instance instanceof $class->name) {
            return true;
        }

        if (!$this->propertyInfo->isWritable($class->name, $property->getName())) {
            return false;
        }

        $value = $this->read($property->getName());
        if (null === $value) {
            return true;
        }

        if (\is_array($value)) {
            return [] === $value;
        }

        return \is_object($value) && $this->isPopulatable($value);
    }

    public function forProperty(PropertySubject $property): ?self
    {
        $reflector = $property->getReflector();
        $declaringClass = $reflector instanceof \ReflectionParameter ? $reflector->getDeclaringClass()?->name : $reflector->class;
        if (null === $declaringClass || !$this->instance instanceof $declaringClass) {
            return null;
        }

        $value = $this->read($property->getName());

        return \is_object($value) && $this->isPopulatable($value) ? new self($value, $this->propertyInfo) : null;
    }

    private function isPopulatable(object $value): bool
    {
        return !$value instanceof \UnitEnum && !$value instanceof \DateTimeInterface && (new \ReflectionObject($value))->isUserDefined();
    }

    /**
     * Reads the backing property through reflection, so a getter that would throw on an uninitialized property is
     * never invoked; only a property without backing property falls back to its getter. Unreadable counts as missing.
     */
    private function read(string $name): mixed
    {
        for ($class = new \ReflectionObject($this->instance); false !== $class; $class = $class->getParentClass()) {
            if (!$class->hasProperty($name)) {
                continue;
            }

            $property = $class->getProperty($name);
            if ($property->isStatic()) {
                break;
            }

            return $property->isInitialized($this->instance) ? $property->getValue($this->instance) : null;
        }

        $readInfo = $this->propertyInfo->getReadInfo($this->instance::class, $name);
        if (PropertyReadInfo::TYPE_METHOD === $readInfo?->getType() && !$readInfo->isStatic()) {
            try {
                return $this->instance->{$readInfo->getName()}();
            } catch (\Error) {
                return null;
            }
        }

        return null;
    }
}
