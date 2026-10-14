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
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\TypeInfo\Type;

/**
 * Describes a class carrying a serializer discriminator map as the union of its mapped classes,
 * each of them restricting the type property to its discriminator values.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DiscriminatorMapSchemaResolver implements ClassSchemaResolverInterface, DefinitionProcessorInterface
{
    private readonly ClassDiscriminatorFromClassMetadata $discriminator;

    public function __construct(?ClassMetadataFactoryInterface $classMetadataFactory = null)
    {
        $this->discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory ?? new ClassMetadataFactory(new AttributeLoader()));
    }

    public function resolve(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): ?array
    {
        $mapping = $this->discriminator->getMappingForClass($class);
        if (null === $mapping || \in_array($class, $mapping->getTypesMapping(), true)) {
            return null;
        }

        $anyOf = [];
        foreach (array_unique($mapping->getTypesMapping()) as $mappedClass) {
            $anyOf[] = $document->describe(Type::object($mappedClass), $config, $parent);
        }

        return ['anyOf' => $anyOf];
    }

    public function process(array $definition, string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent): array
    {
        $mapping = $this->discriminator->getMappingForMappedObject($class);
        if (null === $mapping || [] === $values = array_keys($mapping->getTypesMapping(), $class, true)) {
            return $definition;
        }

        $typeProperty = $mapping->getTypeProperty();
        $definition['properties'][$typeProperty]['enum'] = $values;
        if (!\in_array($typeProperty, $definition['required'] ?? [], true)) {
            $definition['required'][] = $typeProperty;
        }

        return $definition;
    }
}
