<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Subject;

use Symfony\AI\Platform\Contract\JsonSchema\SchemaNameResolver;

/**
 * Metadata for JSON schema property.
 */
final class PropertySubject
{
    public function __construct(
        private readonly string $name,
        private readonly \ReflectionProperty|\ReflectionMethod|\ReflectionParameter $reflector,
    ) {
    }

    /**
     * The PHP property or parameter name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The key of this property in the generated JSON schema, which is the PHP name
     * unless it is renamed with `#[Schema(name: '...')]`.
     */
    public function getSchemaName(): string
    {
        // The same property is described by several reflectors (the property itself, the constructor
        // parameter writing it), and the attribute sits on only one of them, so the class-wide map
        // takes precedence: it is the one that also detects colliding keys.
        $class = $this->getPropertyClass();
        if (null !== $class && isset(SchemaNameResolver::forClass($class)[$this->name])) {
            return SchemaNameResolver::forClass($class)[$this->name];
        }

        return SchemaNameResolver::forReflector($this->reflector, $this->name);
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

    /**
     * The class this subject describes a property of, or null if it does not describe one,
     * e.g. for the parameters of a tool method.
     *
     * @return class-string|null
     */
    private function getPropertyClass(): ?string
    {
        if (!$this->reflector instanceof \ReflectionParameter) {
            return $this->reflector->getDeclaringClass()->name;
        }

        $function = $this->reflector->getDeclaringFunction();
        if (!$function instanceof \ReflectionMethod) {
            return null;
        }

        if (!SchemaNameResolver::describesProperty($function->name)) {
            return null;
        }

        return $function->getDeclaringClass()->name;
    }
}
