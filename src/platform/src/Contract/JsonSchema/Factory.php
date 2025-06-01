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
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
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
 *     }>,
 *     required: list<string>,
 *     additionalProperties: false,
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
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
        if (0 === \count($elements)) {
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
                $schema['type'] = [$schema['type'], 'null'];
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
                if (\in_array($type->getClassName(), ['DateTime', 'DateTimeImmutable', 'DateTimeInterface'], true)) {
                    return ['type' => 'string', 'format' => 'date-time'];
                } else {
                    // Recursively build the schema for an object type
                    return $this->buildProperties($type->getClassName()) ?? ['type' => 'object'];
                }

                // no break
            case $type->isIdentifiedBy(TypeIdentifier::STRING):
            default:
                // Fallback to string for any unhandled types
                return ['type' => 'string'];
        }
    }
}
