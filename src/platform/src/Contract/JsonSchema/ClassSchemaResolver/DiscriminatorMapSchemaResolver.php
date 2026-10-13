<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\ClassSchemaResolver;

use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;
use Symfony\Component\JsonSchema\SchemaGeneratorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\TypeInfo\Type;

/**
 * Describes a class carrying a serializer discriminator map as the union of its mapped classes.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DiscriminatorMapSchemaResolver implements ClassSchemaResolverInterface
{
    private readonly ClassMetadataFactoryInterface $classMetadataFactory;
    private SchemaGeneratorInterface $generator;

    public function __construct(?ClassMetadataFactoryInterface $classMetadataFactory = null)
    {
        $this->classMetadataFactory = $classMetadataFactory ?? new ClassMetadataFactory(new AttributeLoader());
    }

    // The resolver API has no handle to describe nested classes, so it calls back into the generator.
    public function setGenerator(SchemaGeneratorInterface $generator): void
    {
        $this->generator = $generator;
    }

    public function resolve(string $class, Configuration $config, ?DefinitionParent $parent = null): ?array
    {
        $mapping = $this->classMetadataFactory->getMetadataFor($class)->getClassDiscriminatorMapping();
        if (null === $mapping || \in_array($class, $mapping->getTypesMapping(), true)) {
            return null;
        }

        $typeProperty = $mapping->getTypeProperty();
        $anyOf = [];
        foreach ($mapping->getTypesMapping() as $value => $mappedClass) {
            $schema = $this->generator->generate(Type::object($mappedClass), $config)->flatten()->getRoot();
            $schema['properties'][$typeProperty]['enum'] = [$value];
            if (!\in_array($typeProperty, $schema['required'] ?? [], true)) {
                $schema['required'][] = $typeProperty;
            }
            $anyOf[] = $schema;
        }

        return ['anyOf' => $anyOf];
    }
}
