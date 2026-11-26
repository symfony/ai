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
        $schema = $required = $properties = [];
        foreach ($this->objectDescribers as $describer) {
            foreach ($describer->describeObject($subject, $schema) as $property) {
                $properties[$property->getName()][] = $property;
                if ($property->isRequired()) {
                    $required[$property->getName()] = true;
                }
            }
        }

        foreach ($properties as $name => $subjects) {
            $propertySchema = null;
            foreach ($this->propertyDescribers as $describer) {
                foreach ($subjects as $propertySubject) {
                    $describer->describeProperty($propertySubject, $propertySchema);
                }
            }

            $this->finalizeProperty($propertySchema);
            $schema['properties'][$name] = $propertySchema;
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

        $this->finalizeProperty($schema);
    }

    /**
     * @param array<string, mixed>|null $schema
     */
    private function finalizeProperty(?array &$schema): void
    {
        // an enum lists every allowed value, so a nullable property must list null too
        if (isset($schema['enum']) && \in_array('null', (array) ($schema['type'] ?? []), true) && !\in_array(null, $schema['enum'], true)) {
            $schema['enum'][] = null;
        }
    }
}
