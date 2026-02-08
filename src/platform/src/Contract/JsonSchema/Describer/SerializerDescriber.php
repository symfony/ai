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

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Model;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class SerializerDescriber implements ModelDescriberInterface, ModelDescriberAwareInterface
{
    private ModelDescriberInterface $describer;

    public function __construct(
        private readonly ClassMetadataFactoryInterface $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader()),
    ) {
    }

    public function setModelDescriber(ModelDescriberInterface $modelDescriber): void
    {
        $this->describer = $modelDescriber;
    }

    public function describeModel(Model $model, ?array &$schema): iterable
    {
        if (!$model->getReflector() instanceof \ReflectionClass) {
            return [];
        }

        $class = $model->getName();

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
            $type = $schema['type'] ??= 'object';
            $typeProperty = $discriminatorMapping->getTypeProperty();
            foreach ($discriminatorMapping->getTypesMapping() as $discriminatorValue => $discriminatorClass) {
                $subSchema = &$schema['anyOf'][];
                $this->describer->describeModel(new Model($discriminatorClass, new \ReflectionClass($discriminatorClass)), $subSchema);
                $subSchema['properties'][$typeProperty]['const'] = $discriminatorValue;
                if ($type === ($subSchema['type'] ?? null)) {
                    unset($subSchema['type']);
                }
            }
        }

        return [];
    }
}
