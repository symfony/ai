<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

/**
 * The properties of an object a structured output call asks for.
 *
 * A property selected without nested selection is asked for in full. A nested selection narrows an object
 * the instance already holds, and carries its discriminator when that object is one of several mapped classes.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PropertySelection
{
    /**
     * @param array<string, PropertySelection|null> $properties
     */
    public function __construct(
        private readonly array $properties,
        private readonly ?string $discriminatorProperty = null,
        private readonly ?string $discriminatorValue = null,
    ) {
    }

    /**
     * @return array<string, PropertySelection|null>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getDiscriminatorProperty(): ?string
    {
        return $this->discriminatorProperty;
    }

    public function getDiscriminatorValue(): ?string
    {
        return $this->discriminatorValue;
    }

    /**
     * The selection in the format of the serializer's `attributes` context, e.g. `['title', 'destination' => ['mayor']]`.
     *
     * @return array<int|string, mixed>
     */
    public function toAttributes(): array
    {
        $attributes = [];
        foreach ($this->properties as $name => $selection) {
            if (null === $selection) {
                $attributes[] = $name;
                continue;
            }

            $attributes[$name] = $selection->toAttributes();
        }

        return $attributes;
    }
}
