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

        if (!$subject->getReflector() instanceof \ReflectionClass) {
            return [];
        }

        $class = $subject->getReflector()->name;
        foreach ($this->propertyListExtractor->getProperties($class, ['serializer_groups' => $this->serializerGroups]) ?? [] as $propertyName) {
            if (!$this->propertyInfo->isWritable($class, $propertyName) && !$this->propertyInfo->isInitializable($class, $propertyName)) {
                continue;
            }

            $readInfo = $this->propertyReadWriteInfo->getReadInfo($class, $propertyName);
            if ($readInfo) {
                $readReflector = match ($readInfo->getType()) {
                    PropertyReadInfo::TYPE_METHOD => new \ReflectionMethod($class, $readInfo->getName()),
                    default => new \ReflectionProperty($class, $readInfo->getName()),
                };

                yield new PropertySubject($propertyName, $readReflector);
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
                yield new PropertySubject($propertyName, $writeReflector);
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
}
