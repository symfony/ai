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
use Symfony\AI\Platform\Exception\InvalidArgumentException;

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
        return self::declaredName($reflector) ?? $default;
    }

    /**
     * Returns the renamed properties of a class, indexed by PHP property name.
     *
     * Only properties actually carrying a `#[Schema(name: ...)]` are part of the map.
     *
     * @param class-string $class
     *
     * @return array<string, string> PHP property name => JSON key
     *
     * @throws InvalidArgumentException When two members of the class rename the same property
     *                                  differently, or when two properties end up on the same JSON key
     */
    public static function forClass(string $class): array
    {
        if (isset(self::$classMaps[$class])) {
            return self::$classMaps[$class];
        }

        $reflection = new \ReflectionClass($class);

        /** @var array<string, string> $members PHP property name => member declaring it */
        $members = [];
        /** @var array<string, array{name: string, member: string}> $renames PHP property name => rename and the member declaring it */
        $renames = [];

        foreach ($reflection->getProperties() as $property) {
            self::collect($class, $members, $renames, $property->name, $property, '$'.$property->name);
        }

        // Only single-argument setters map back to a property name; a tool method is resolved
        // from its reflector directly.
        foreach ($reflection->getMethods() as $method) {
            $propertyName = self::propertyNameOfMutator($method->name);
            if (null === $propertyName || 1 !== $method->getNumberOfParameters()) {
                continue;
            }

            self::collect($class, $members, $renames, $propertyName, $method->getParameters()[0], $method->name.'()');
        }

        if ($constructor = $reflection->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                self::collect($class, $members, $renames, $parameter->name, $parameter, '__construct($'.$parameter->name.')');
            }
        }

        $map = [];
        /** @var array<string, string> $owners JSON key => member owning it */
        $owners = [];

        foreach ($members as $propertyName => $member) {
            $name = $renames[$propertyName]['name'] ?? $propertyName;
            $owner = $renames[$propertyName]['member'] ?? $member;

            if (isset($owners[$name])) {
                throw new InvalidArgumentException(\sprintf('Class "%s" maps both %s and %s to the JSON key "%s", every "#[Schema(name: ...)]" must resolve to a key that is not used by another property.', $class, $owners[$name], $owner, $name));
            }

            $owners[$name] = $owner;

            if ($name !== $propertyName) {
                $map[$propertyName] = $name;
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

    /**
     * Registers a member describing $propertyName and the JSON key it explicitly declares, if any.
     *
     * A promoted constructor property is reported twice, once as property and once as parameter,
     * carrying the very same attribute, which is not a conflict.
     *
     * @param array<string, string>                              $members
     * @param array<string, array{name: string, member: string}> $renames
     */
    private static function collect(string $class, array &$members, array &$renames, string $propertyName, \ReflectionProperty|\ReflectionParameter $reflector, string $member): void
    {
        $members[$propertyName] ??= $member;

        $name = self::declaredName($reflector);
        if (null === $name) {
            return;
        }

        if (!isset($renames[$propertyName])) {
            $renames[$propertyName] = ['name' => $name, 'member' => $member];

            return;
        }

        if ($renames[$propertyName]['name'] === $name) {
            return;
        }

        throw new InvalidArgumentException(\sprintf('Property "%s" of class "%s" is renamed twice, %s declares the JSON key "%s" while %s declares "%s".', $propertyName, $class, $renames[$propertyName]['member'], $renames[$propertyName]['name'], $member, $name));
    }

    /**
     * The JSON key explicitly declared by a `#[Schema(name: ...)]` on the given reflector.
     */
    private static function declaredName(\ReflectionProperty|\ReflectionMethod|\ReflectionParameter $reflector): ?string
    {
        foreach ($reflector->getAttributes(Schema::class) as $attribute) {
            $name = $attribute->newInstance()->name;
            if (null !== $name) {
                return $name;
            }
        }

        return null;
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
