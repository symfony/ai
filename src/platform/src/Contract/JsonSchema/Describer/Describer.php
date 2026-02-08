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

final class Describer implements ObjectDescriberInterface, PropertyDescriberInterface
{
    /** @var iterable<ObjectDescriberInterface> */
    private readonly iterable $objectDescribers;
    /** @var iterable<PropertyDescriberInterface> */
    private readonly iterable $propertyDescribers;

    /**
     * @param iterable<ObjectDescriberInterface|PropertyDescriberInterface> $describers
     */
    public function __construct(
        iterable $describers = [
            new SerializerDescriber(),
            new TypeInfoDescriber(),
            new MethodDescriber(),
            new PropertyInfoDescriber(),
            new WithAttributeDescriber(),
        ],
    ) {
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
        $schema = $required = [];
        foreach ($this->objectDescribers as $describer) {
            foreach ($describer->describeObject($subject, $schema) as $property) {
                $this->describeProperty($property, $schema['properties'][$property->getName()]);
                if ($property->isRequired()) {
                    $required[$property->getName()] = true;
                }
            }
        }

        if (['type' => 'object'] === $schema) {
            $schema = null;
        }

        if ($required) {
            $schema['required'] = array_keys($required);
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
}
