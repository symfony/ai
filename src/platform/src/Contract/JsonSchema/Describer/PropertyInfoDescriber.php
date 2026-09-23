<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * Describes model & properties using symfony/property-info.
 */
final class PropertyInfoDescriber implements ObjectDescriberInterface, PropertyDescriberInterface
{
    private readonly PropertyDescriptionExtractorInterface $propertyDescriptionExtractor;

    /**
     * @param list<string> $serializerGroups
     */
    public function __construct(
        private readonly PropertyListExtractorInterface $propertyListExtractor = new SerializerExtractor(new ClassMetadataFactory(new AttributeLoader())),
        private readonly PropertyAccessExtractorInterface&PropertyInitializableExtractorInterface $propertyInfo = new ReflectionExtractor(),
        private readonly PropertyWriteInfoExtractorInterface&PropertyReadInfoExtractorInterface $propertyReadWriteInfo = new ReflectionExtractor(),
        ?PropertyDescriptionExtractorInterface $propertyDescriptionExtractor = null,
        private readonly array $serializerGroups = ['*'],
    ) {
        $this->propertyDescriptionExtractor = $propertyDescriptionExtractor ?? new PropertyInfoExtractor([], [], [new PhpDocExtractor()], [], [new ReflectionExtractor()]);
    }

    public function describeObject(ObjectSubject $subject, ?array &$schema): iterable
    {
        if (!\in_array('object', (array) ($schema['type'] ?? 'object'))) {
            return [];
        }

        $reflection = $subject->getReflector();
        if (!$reflection instanceof \ReflectionClass) {
            return [];
        }

        $class = $reflection->name;
        $context = $subject->getContext();

        // A per-call `serializer_groups` context narrows the schema to the given groups,
        // falling back to the groups this describer was configured with.
        $serializerGroups = $context['serializer_groups'] ?? $this->serializerGroups;

        // A per-call `populate_instance` context narrows the schema to what that instance is still missing,
        // as long as it is of the described class (it is not, e.g., for the other branches of an `anyOf`).
        $instance = $context['populate_instance'] ?? null;
        if (!$instance instanceof $class) {
            $instance = null;
        }
        unset($context['populate_instance']);

        foreach ($this->propertyListExtractor->getProperties($class, ['serializer_groups' => $serializerGroups]) ?? [] as $propertyName) {
            if (!$this->propertyInfo->isWritable($class, $propertyName) && !$this->propertyInfo->isInitializable($class, $propertyName)) {
                continue;
            }

            $readInfo = $this->propertyReadWriteInfo->getReadInfo($class, $propertyName);

            $propertyContext = $context;
            if (null !== $instance) {
                // A constructor-only (e.g. readonly) property can never be set on an existing instance
                if (!$this->propertyInfo->isWritable($class, $propertyName)) {
                    continue;
                }

                $value = $this->readValue($instance, $reflection, $propertyName, $readInfo);
                if (\is_object($value)) {
                    $propertyContext['populate_instance'] = $value;
                }

                if (!$this->shouldPopulate($value, $propertyContext)) {
                    continue;
                }
            }

            if ($readInfo) {
                $readReflector = match ($readInfo->getType()) {
                    PropertyReadInfo::TYPE_METHOD => new \ReflectionMethod($class, $readInfo->getName()),
                    default => new \ReflectionProperty($class, $readInfo->getName()),
                };

                yield new PropertySubject($propertyName, $readReflector, $propertyContext);
            }

            $writeInfo = $this->propertyReadWriteInfo->getWriteInfo($class, $propertyName);
            if ($writeInfo?->getType() === $readInfo?->getType() && $writeInfo?->getName() === $readInfo?->getName()) {
                continue;
            }

            $writeReflector = match ($writeInfo?->getType()) {
                PropertyWriteInfo::TYPE_METHOD => new \ReflectionParameter([$class, $writeInfo->getName()], 0),
                PropertyWriteInfo::TYPE_CONSTRUCTOR => new \ReflectionParameter([$class, '__construct'], $writeInfo->getName()),
                PropertyWriteInfo::TYPE_ADDER_AND_REMOVER => new \ReflectionParameter([$class, $writeInfo->getAdderInfo()->getName()], 0),
                PropertyWriteInfo::TYPE_PROPERTY => new \ReflectionProperty($class, $writeInfo->getName()),
                default => null,
            };
            if ($writeReflector) {
                yield new PropertySubject($propertyName, $writeReflector, $propertyContext);
            }
        }
    }

    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        $reflector = $subject->getReflector();
        if ($reflector instanceof \ReflectionParameter) {
            return;
        }

        if ($description = $this->propertyDescriptionExtractor->getShortDescription($reflector->class, $subject->getName())) {
            $schema['description'] = $description;
        }
    }

    /**
     * Reads the backing property through reflection, so a getter that would throw on an uninitialized property
     * is never invoked; only a property without backing property falls back to its getter. Unreadable counts as missing.
     *
     * @param \ReflectionClass<covariant object> $class
     */
    private function readValue(object $instance, \ReflectionClass $class, string $propertyName, ?PropertyReadInfo $readInfo): mixed
    {
        if ($class->hasProperty($propertyName) && !$class->getProperty($propertyName)->isStatic()) {
            $property = $class->getProperty($propertyName);

            return $property->isInitialized($instance) ? $property->getValue($instance) : null;
        }

        if (PropertyReadInfo::TYPE_METHOD === $readInfo?->getType() && !$readInfo->isStatic()) {
            try {
                return $class->getMethod($readInfo->getName())->invoke($instance);
            } catch (\Error) {
                return null;
            }
        }

        return null;
    }

    /**
     * A value is left to the model when it is uninitialized, `null` or an empty array; anything else, including
     * `''`, `0` and `false`, is taken as given. A nested object is decided by its own properties instead.
     *
     * @param array<string, mixed> $context
     */
    private function shouldPopulate(mixed $value, array $context): bool
    {
        if (null === $value) {
            return true;
        }

        if (\is_array($value)) {
            return [] === $value;
        }

        if (!\is_object($value)) {
            return false;
        }

        $reflection = new \ReflectionClass($value);
        if ($value instanceof \UnitEnum || $value instanceof \DateTimeInterface || !$reflection->isUserDefined()) {
            return false;
        }

        $schema = null;
        foreach ($this->describeObject(new ObjectSubject($value::class, $reflection, $context), $schema) as $property) {
            return true;
        }

        return false;
    }
}
