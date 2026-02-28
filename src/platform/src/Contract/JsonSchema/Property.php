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

/**
 * Metadata for JSON schema property.
 */
final class Property
{
    public function __construct(
        private readonly string $name,
        private readonly \ReflectionProperty|\ReflectionMethod|\ReflectionParameter $reflector,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getReflector(): \ReflectionParameter|\ReflectionMethod|\ReflectionProperty
    {
        return $this->reflector;
    }

    public function isRequired(): bool
    {
        return match (true) {
            $this->reflector instanceof \ReflectionParameter => !$this->reflector->isOptional(),
            $this->reflector instanceof \ReflectionProperty => true,
            $this->reflector instanceof \ReflectionMethod => false,
        };
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T[]
     */
    public function getAttributes(string $class): array
    {
        return array_map(static fn (\ReflectionAttribute $attribute) => $attribute->newInstance(), $this->reflector->getAttributes($class));
    }
}
