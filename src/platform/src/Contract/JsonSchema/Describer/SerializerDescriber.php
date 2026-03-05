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
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class SerializerDescriber implements ObjectDescriberInterface, ObjectDescriberAwareInterface
{
    private ObjectDescriberInterface $describer;

    public function __construct(
        private readonly ClassMetadataFactoryInterface $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader()),
    ) {
    }

    public function setObjectDescriber(ObjectDescriberInterface $describer): void
    {
        $this->describer = $describer;
    }

    public function describeObject(ObjectSubject $subject, ?array &$schema): iterable
    {
        if (!$subject->getReflector() instanceof \ReflectionClass) {
            return [];
        }

        $class = $subject->getName();

        if (!$this->classMetadataFactory->hasMetadataFor($class)) {
            return [];
        }

        // Handle DateTimeNormalizer logic
        if (\in_array($class, ['DateTime', 'DateTimeImmutable', 'DateTimeInterface'], true)) {
            $schema['type'] = 'string';
            $schema['format'] = 'date-time';

            return [];
        }

        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);

        $discriminatorMapping = $classMetadata->getClassDiscriminatorMapping();
        if ($discriminatorMapping) {
            $typeProperty = $discriminatorMapping->getTypeProperty();
            foreach ($discriminatorMapping->getTypesMapping() as $discriminatorValue => $discriminatorClass) {
                $subSchema = &$schema['anyOf'][];
                $this->describer->describeObject(new ObjectSubject($discriminatorClass, new \ReflectionClass($discriminatorClass)), $subSchema);
                $subSchema['properties'][$typeProperty]['enum'] = [$discriminatorValue];
            }
        }

        return [];
    }
}
