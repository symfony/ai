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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * Resolves the JSON key of a property or parameter, honoring `#[Schema(name: ...)]`.
 *
 * This is the single source of truth for the key renaming: schema generation
 * (`Describer`) and hydration (`StructuredOutput\Serializer`) both go through it,
 * so the generated schema and the deserialization always agree on the key.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SchemaNameResolver
{
    /**
     * @var array<class-string, array<string, string>>
     */
    private static array $classMaps = [];

    /**
     * Returns the JSON key to use for the given reflector, falling back to $default.
     */
    public static function forReflector(\ReflectionProperty|\ReflectionMethod|\ReflectionParameter $reflector, string $default): string
    {
        foreach ($reflector->getAttributes(Schema::class) as $attribute) {
            $name = $attribute->newInstance()->name;
            if (null !== $name) {
                return $name;
            }
        }

        return $default;
    }

    /**
     * Returns the renamed properties of a class, indexed by PHP property name.
     *
     * Only properties actually carrying a `#[Schema(name: ...)]` are part of the map.
     *
     * @param class-string $class
     *
     * @return array<string, string> PHP property name => JSON key
     */
    public static function forClass(string $class): array
    {
        if (isset(self::$classMaps[$class])) {
            return self::$classMaps[$class];
        }

        $reflection = new \ReflectionClass($class);
        $map = [];

        foreach ($reflection->getProperties() as $property) {
            $name = self::forReflector($property, $property->name);
            if ($name !== $property->name) {
                $map[$property->name] = $name;
            }
        }

        // Only single-argument setters map back to a property name; a tool method is resolved
        // from its reflector directly.
        foreach ($reflection->getMethods() as $method) {
            $propertyName = self::propertyNameOfMutator($method->name);
            if (null === $propertyName || 1 !== $method->getNumberOfParameters()) {
                continue;
            }

            $name = self::forReflector($method->getParameters()[0], $propertyName);
            if ($name !== $propertyName) {
                $map[$propertyName] = $name;
            }
        }

        if ($constructor = $reflection->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                $name = self::forReflector($parameter, $parameter->name);
                if ($name !== $parameter->name) {
                    $map[$parameter->name] = $name;
                }
            }
        }

        return self::$classMaps[$class] = $map;
    }

    /**
     * Whether the parameters of the given method declare a property, which is the case for
     * the constructor and for setters like `setFoo()`.
     */
    public static function describesProperty(string $methodName): bool
    {
        if ('__construct' === $methodName) {
            return true;
        }

        return null !== self::propertyNameOfMutator($methodName);
    }

    private static function propertyNameOfMutator(string $methodName): ?string
    {
        if (!str_starts_with($methodName, 'set')) {
            return null;
        }

        $propertyName = lcfirst(substr($methodName, 3));
        if ('' === $propertyName) {
            return null;
        }

        return $propertyName;
    }
}
