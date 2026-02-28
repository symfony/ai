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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class TypeInfoDescriber implements DescriberInterface, FactoryAwareInterface
{
    private Factory $factory;
    private TypeResolverInterface $typeResolver;

    public function __construct(?TypeResolverInterface $typeResolver = null)
    {
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
    }

    public function setFactory(Factory $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * @param-out JsonSchema|array<mixed> $schema
     */
    public function describe(\ReflectionProperty|\ReflectionParameter|\ReflectionClass $reflector, ?array &$schema): void
    {
        if ($reflector instanceof \ReflectionClass) {
            $subSchema = ['type' => 'object'];
        } else {
            $type = $this->typeResolver->resolve($reflector);
            $subSchema = $this->getTypeSchema($type);
            if ($type->isNullable()) {
                if (!isset($subSchema['anyOf'])) {
                    $subSchema['type'] = (array) $subSchema['type'];
                    $subSchema['type'][] = 'null';
                }
            }
        }

        $schema = array_merge_recursive($schema ?? [], $subSchema);
    }

    /**
     * @return array<string, mixed>
     */
    private function getTypeSchema(Type $type): array
    {
        // Handle BackedEnumType directly
        if ($type instanceof BackedEnumType) {
            return $this->buildEnumSchema($type->getClassName());
        }

        // Handle NullableType that wraps a BackedEnumType
        if ($type instanceof NullableType) {
            $wrappedType = $type->getWrappedType();
            if ($wrappedType instanceof BackedEnumType) {
                return $this->buildEnumSchema($wrappedType->getClassName());
            }
        }

        if ($type instanceof UnionType) {
            // Do not handle nullables as a union but directly return the wrapped type schema
            if (2 === \count($type->getTypes()) && $type->isNullable() && $type instanceof NullableType) {
                return $this->getTypeSchema($type->getWrappedType());
            }

            $variants = [];

            foreach ($type->getTypes() as $variant) {
                $variants[] = $this->getTypeSchema($variant);
            }

            return ['anyOf' => $variants];
        }

        switch (true) {
            case $type->isIdentifiedBy(TypeIdentifier::INT):
                return ['type' => 'integer'];

            case $type->isIdentifiedBy(TypeIdentifier::FLOAT):
                return ['type' => 'number'];

            case $type->isIdentifiedBy(TypeIdentifier::BOOL):
                return ['type' => 'boolean'];

            case $type->isIdentifiedBy(TypeIdentifier::ARRAY):
                \assert($type instanceof CollectionType);

                return [
                    'type' => 'array',
                    'items' => $this->getTypeSchema($type->getCollectionValueType()),
                ];

            case $type->isIdentifiedBy(TypeIdentifier::OBJECT):
                if ($type instanceof BuiltinType) {
                    throw new InvalidArgumentException('Cannot build schema from plain object type.');
                }
                \assert($type instanceof ObjectType);

                $className = $type->getClassName();

                if (\in_array($className, ['DateTime', 'DateTimeImmutable', 'DateTimeInterface'], true)) {
                    return ['type' => 'string', 'format' => 'date-time'];
                }

                // Recursively build the schema for an object type
                return $this->factory->buildProperties($className) ?? ['type' => 'object'];

            case $type->isIdentifiedBy(TypeIdentifier::NULL):
                return ['type' => 'null'];
            case $type->isIdentifiedBy(TypeIdentifier::STRING):
            default:
                // Fallback to string for any unhandled types
                return ['type' => 'string'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEnumSchema(string $enumClassName): array
    {
        $reflection = new \ReflectionEnum($enumClassName);

        if (!$reflection->isBacked()) {
            throw new InvalidArgumentException(\sprintf('Enum "%s" is not backed.', $enumClassName));
        }

        $cases = $reflection->getCases();
        $values = [];
        $backingType = $reflection->getBackingType();

        foreach ($cases as $case) {
            $values[] = $case->getBackingValue();
        }

        if (null === $backingType) {
            throw new InvalidArgumentException(\sprintf('Backed enum "%s" has no backing type.', $enumClassName));
        }

        $typeName = $backingType->getName();
        $jsonType = 'string' === $typeName ? 'string' : ('int' === $typeName ? 'integer' : 'string');

        return [
            'type' => $jsonType,
            'enum' => $values,
        ];
    }
}
