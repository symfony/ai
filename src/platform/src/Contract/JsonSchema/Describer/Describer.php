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

use Symfony\AI\Platform\Contract\JsonSchema\Model;
use Symfony\AI\Platform\Contract\JsonSchema\Property;

final class Describer implements ModelDescriberInterface, PropertyDescriberInterface
{
    /** @var iterable<ModelDescriberInterface> */
    private readonly iterable $modelDescribers;
    /** @var iterable<PropertyDescriberInterface> */
    private readonly iterable $propertyDescribers;

    /**
     * @param iterable<ModelDescriberInterface|PropertyDescriberInterface> $describers
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
        $modelDescribers = $propertyDescribers = [];

        foreach ($describers as $describer) {
            if ($describer instanceof ModelDescriberAwareInterface) {
                $describer->setModelDescriber($this);
            }
            if ($describer instanceof ModelDescriberInterface) {
                $modelDescribers[] = $describer;
            }
            if ($describer instanceof PropertyDescriberInterface) {
                $propertyDescribers[] = $describer;
            }
        }

        $this->modelDescribers = $modelDescribers;
        $this->propertyDescribers = $propertyDescribers;
    }

    public function describeModel(Model $model, ?array &$schema): iterable
    {
        $schema = $required = [];
        foreach ($this->modelDescribers as $describer) {
            foreach ($describer->describeModel($model, $schema) as $property) {
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

    public function describeProperty(Property $property, ?array &$schema): void
    {
        foreach ($this->propertyDescribers as $describer) {
            $describer->describeProperty($property, $schema);
        }
    }
}
