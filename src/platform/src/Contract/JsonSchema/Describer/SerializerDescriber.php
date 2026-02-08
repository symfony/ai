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
use Symfony\AI\Platform\Contract\JsonSchema\FactoryAwareInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class SerializerDescriber implements DescriberInterface, FactoryAwareInterface
{
    private Factory $factory;

    public function __construct(
        private readonly ClassMetadataFactoryInterface $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader()),
    ) {
    }

    public function setFactory(Factory $factory): void
    {
        $this->factory = $factory;
    }

    public function describe(\ReflectionProperty|\ReflectionParameter|\ReflectionClass $reflector, ?array &$schema): void
    {
        if (!$reflector instanceof \ReflectionClass) {
            return;
        }

        $discriminatorMapping = $this->classMetadataFactory->getMetadataFor($reflector->name)->getClassDiscriminatorMapping();
        if ($discriminatorMapping) {
            $type = $schema['type'] ?? null;
            foreach ($discriminatorMapping->getTypesMapping() as $discriminatorValue => $discriminatorClass) {
                $subSchema = $this->factory->buildProperties($discriminatorClass);
                $subSchema['properties'][$discriminatorMapping->getTypeProperty()]['const'] = $discriminatorValue;
                if (null !== $type && $type === ($subSchema['type'] ?? null)) {
                    unset($subSchema['type']);
                }
                $schema['anyOf'][] = $subSchema;
            }
        }
    }
}
