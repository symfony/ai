<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * @phpstan-type JsonSchema array{
 *     type: 'object',
 *     properties: array<string, array{
 *         type: string,
 *         description: string,
 *         enum?: list<string>,
 *         const?: string|int|list<string>,
 *         pattern?: string,
 *         minLength?: int,
 *         maxLength?: int,
 *         minimum?: int,
 *         maximum?: int,
 *         multipleOf?: int,
 *         exclusiveMinimum?: int,
 *         exclusiveMaximum?: int,
 *         minItems?: int,
 *         maxItems?: int,
 *         uniqueItems?: bool,
 *         minContains?: int,
 *         maxContains?: int,
 *         required?: bool,
 *         minProperties?: int,
 *         maxProperties?: int,
 *         dependentRequired?: bool,
 *         anyOf?: list<mixed>,
 *     }>,
 *     required: list<string>,
 *     additionalProperties: false,
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final readonly class Factory
{
    private TypeResolver $typeResolver;

    public function __construct(
        private DescriptionParser $descriptionParser = new DescriptionParser(),
        ?TypeResolver $typeResolver = null,
    ) {
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
    }

    /**
     * @return JsonSchema|null
     */
    public function buildParameters(string $className, string $methodName): ?array
    {
        $reflection = new \ReflectionMethod($className, $methodName);

        return $this->convertTypes($reflection->getParameters());
    }

    /**
     * @return JsonSchema|null
     */
    public function buildProperties(string $className): ?array
    {
        $reflection = new \ReflectionClass($className);

        return $this->convertTypes($reflection->getProperties());
    }

    /**
     * @param list<\ReflectionProperty|\ReflectionParameter> $elements
     *
     * @return JsonSchema|null
     */
    private function convertTypes(array $elements): ?array
    {
        if ([] === $elements) {
            return null;
        }

        $result = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => false,
        ];

        foreach ($elements as $element) {
            $name = $element->getName();
            $type = $this->typeResolver->resolve($element);
            $schema = $this->getTypeSchema($type);

            if ($type->isNullable()) {
                // anyOf already contains the null variant when applicable; do nothing
                if (!isset($schema['anyOf'])) {
                    $schema['type'] = [$schema['type'], 'null'];
                }
            } elseif (!($element instanceof \ReflectionParameter && $element->isOptional())) {
                $result['required'][] = $name;
            }

            $description = $this->descriptionParser->getDescription($element);
            if ('' !== $description) {
                $schema['description'] = $description;
            }

            // Check for ToolParameter attributes
            $attributes = $element->getAttributes(With::class);
            if (\count($attributes) > 0) {
                $attributeState = array_filter((array) $attributes[0]->newInstance(), fn ($value) => null !== $value);
                $schema = array_merge($schema, $attributeState);
            }

            $result['properties'][$name] = $schema;
        }

        return $result;
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
                $collectionValueType = $type->getCollectionValueType();

                if ($collectionValueType->isIdentifiedBy(TypeIdentifier::OBJECT)) {
                    \assert($collectionValueType instanceof ObjectType);

                    // Check for the DiscriminatorMap attribute to handle polymorphic arrays
                    $discriminatorMapping = $this->findDiscriminatorMapping($collectionValueType->getClassName());
                    if ($discriminatorMapping) {
                        $discriminators = [];
                        foreach ($discriminatorMapping as $_ => $discriminator) {
                            $discriminators[] = $this->buildProperties($discriminator);
                        }

                        return [
                            'type' => 'array',
                            'items' => [
                                'anyOf' => $discriminators,
                            ],
                        ];
                    }

                    return [
                        'type' => 'array',
                        'items' => $this->buildProperties($collectionValueType->getClassName()),
                    ];
                }

                return [
                    'type' => 'array',
                    'items' => $this->getTypeSchema($collectionValueType),
                ];

            case $type->isIdentifiedBy(TypeIdentifier::OBJECT):
                if ($type instanceof BuiltinType) {
                    throw new InvalidArgumentException('Cannot build schema from plain object type.');
                }
                \assert($type instanceof ObjectType);

                $className = $type->getClassName();

                if (\in_array($className, ['DateTime', 'DateTimeImmutable', 'DateTimeInterface'], true)) {
                    return ['type' => 'string', 'format' => 'date-time'];
                } else {
                    // Recursively build the schema for an object type
                    return $this->buildProperties($className) ?? ['type' => 'object'];
                }

                // no break
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

    /**
     * @param class-string $className
     *
     * @return array<string, class-string>|null
     *
     * @throws \ReflectionException
     */
    private function findDiscriminatorMapping(string $className): ?array
    {
        /** @var \ReflectionAttribute<DiscriminatorMap>[] $attributes */
        $attributes = (new \ReflectionClass($className))->getAttributes(DiscriminatorMap::class);
        $result = \count($attributes) > 0 ? $attributes[array_key_first($attributes)]->newInstance() : null;

        if (!$result) {
            return null;
        }

        /**
         * In the 8.* release of symfony/serializer DiscriminatorMap removes the getMapping() method in favor of property access.
         * This satisfies the project's pipeline that builds against both < and >= 8.* release.
         * This logic can be removed once the project builds against >= 8.* only.
         *
         * @see https://github.com/symfony/ai/pull/585#issuecomment-3303631346
         */
        $reflectionProperty = new \ReflectionProperty($result, 'mapping');
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($result);
    }
}
