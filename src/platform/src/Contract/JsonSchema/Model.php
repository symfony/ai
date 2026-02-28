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

final class Model
{
    /**
     * @param class-string|string                                  $name
     * @param \ReflectionClass<covariant object>|\ReflectionMethod $reflector
     */
    public function __construct(private string $name, private \ReflectionClass|\ReflectionMethod $reflector)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \ReflectionClass<covariant object>|\ReflectionMethod
     */
    public function getReflector(): \ReflectionClass|\ReflectionMethod
    {
        return $this->reflector;
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
