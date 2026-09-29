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
use Symfony\AI\Platform\Contract\JsonSchema\Selector\PropertySelectorInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class Describer implements ObjectDescriberInterface, PropertyDescriberInterface
{
    /** @var iterable<ObjectDescriberInterface> */
    private readonly iterable $objectDescribers;
    /** @var iterable<PropertyDescriberInterface> */
    private readonly iterable $propertyDescribers;

    /**
     * @param iterable<ObjectDescriberInterface|PropertyDescriberInterface>|null $describers
     */
    public function __construct(
        ?iterable $describers = null,
    ) {
        if (null === $describers) {
            $describers = [
                new SerializerDescriber(),
                new TypeInfoDescriber(),
                new MethodDescriber(),
                new PropertyInfoDescriber(),
            ];

            if (interface_exists(ValidatorInterface::class)) {
                $describers[] = new ValidatorConstraintsDescriber();
            }

            $describers[] = new SchemaAttributeDescriber();
        }

        $objectDescribers = $propertyDescribers = [];

        foreach ($describers as $describer) {
            if ($describer instanceof ObjectDescriberAwareInterface) {
                $describer->setObjectDescriber($this);
            }
            if ($describer instanceof ObjectDescriberInterface) {
                $objectDescribers[] = $describer;
            }
            if ($describer instanceof PropertyDescriberInterface) {
                $propertyDescribers[] = $describer;
            }
        }

        $this->objectDescribers = $objectDescribers;
        $this->propertyDescribers = $propertyDescribers;
    }

    public function describeObject(ObjectSubject $subject, ?array &$schema): iterable
    {
        $selector = $subject->getContext()[Factory::CONTEXT_SELECTOR] ?? null;
        if (!$selector instanceof PropertySelectorInterface) {
            $selector = null;
        }

        $schema = $required = $populatedInPlace = [];
        foreach ($this->objectDescribers as $describer) {
            foreach ($describer->describeObject($subject, $schema) as $property) {
                if (null !== $selector) {
                    if (!$selector->isOpen($subject, $property)) {
                        continue;
                    }

                    // The selector for the object this property holds travels in the property's context
                    $nestedSelector = $selector->forProperty($property);
                    $property = $property->withContext([Factory::CONTEXT_SELECTOR => $nestedSelector] + $property->getContext());
                    if (null !== $nestedSelector) {
                        $populatedInPlace[$property->getName()] = true;
                    }
                }

                $this->describeProperty($property, $schema['properties'][$property->getName()]);
                if ($property->isRequired()) {
                    $required[$property->getName()] = true;
                }
            }
        }

        foreach (array_keys($populatedInPlace) as $name) {
            if (!$this->narrowPopulatedObject($schema['properties'][$name])) {
                unset($schema['properties'][$name], $required[$name]);
            }
        }
        if ([] === ($schema['properties'] ?? null)) {
            unset($schema['properties']);
        }

        if (['type' => 'object'] === $schema) {
            $schema = null;
        }

        if ($required) {
            $schema['required'] = array_keys($required);
        }

        if (isset($schema['properties']) && !isset($schema['additionalProperties'])) {
            $schema['additionalProperties'] = false;
        }

        return [];
    }

    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        foreach ($this->propertyDescribers as $describer) {
            $describer->describeProperty($subject, $schema);
        }
    }

    /**
     * An object populated in place is not worth describing when nothing on it is open, and must never be
     * answered with `null`, as that would replace the instance and the values it already holds.
     *
     * @param array<string, mixed> $schema
     *
     * @return bool Whether the property is kept
     */
    private function narrowPopulatedObject(array &$schema): bool
    {
        if (!isset($schema['properties']) && !isset($schema['anyOf'])) {
            return false;
        }

        if (isset($schema['type']) && \is_array($schema['type'])) {
            $types = array_values(array_diff($schema['type'], ['null']));
            $schema['type'] = 1 === \count($types) ? $types[0] : $types;
        }
        if (isset($schema['anyOf']) && \is_array($schema['anyOf'])) {
            $schema['anyOf'] = array_values(array_filter($schema['anyOf'], static fn (mixed $variant): bool => ['type' => 'null'] !== $variant));
        }

        return true;
    }
}
