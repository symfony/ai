<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

/**
 * The API of every package of the monorepo, with inheritance resolved across packages.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Api
{
    /** @var array<string, array<string, MethodApi>> */
    private array $effectiveMethods = [];

    /** @var array<string, list<string>> */
    private array $ancestors = [];

    /**
     * @param array<string, ClassApi> $classes keyed by lowercase name
     */
    public function __construct(
        public readonly array $classes,
    ) {
    }

    public function get(string $name): ?ClassApi
    {
        return $this->classes[strtolower(ltrim($name, '\\'))] ?? null;
    }

    /**
     * Own methods merged with the ones inherited from classes, traits and interfaces of the monorepo.
     *
     * @return array<string, MethodApi> keyed by lowercase name
     */
    public function methods(ClassApi $class): array
    {
        $key = strtolower($class->name);
        if (isset($this->effectiveMethods[$key])) {
            return $this->effectiveMethods[$key];
        }
        $this->effectiveMethods[$key] = [];

        $methods = [];
        foreach ($class->interfaces as $interface) {
            if (null !== $parent = $this->get($interface)) {
                $methods = array_merge($methods, $this->methods($parent));
            }
        }
        if (null !== $class->parent && null !== $parent = $this->get($class->parent)) {
            $methods = array_merge($methods, array_filter($this->methods($parent), static fn (MethodApi $method): bool => 'private' !== $method->visibility));
        }
        foreach ($class->traits as $trait) {
            if (null !== $used = $this->get($trait)) {
                $methods = array_merge($methods, $this->methods($used));
            }
        }

        return $this->effectiveMethods[$key] = array_merge($methods, $class->methods);
    }

    /**
     * @return array<string, PropertyApi>
     */
    public function properties(ClassApi $class): array
    {
        $properties = [];
        if (null !== $class->parent && null !== $parent = $this->get($class->parent)) {
            $properties = array_filter($this->properties($parent), static fn (PropertyApi $property): bool => 'private' !== $property->visibility);
        }
        foreach ($class->traits as $trait) {
            if (null !== $used = $this->get($trait)) {
                $properties = array_merge($properties, $this->properties($used));
            }
        }

        return array_merge($properties, $class->properties);
    }

    /**
     * @return array<string, ConstantApi>
     */
    public function constants(ClassApi $class): array
    {
        $constants = [];
        foreach ($class->interfaces as $interface) {
            if (null !== $parent = $this->get($interface)) {
                $constants = array_merge($constants, $this->constants($parent));
            }
        }
        if (null !== $class->parent && null !== $parent = $this->get($class->parent)) {
            $constants = array_merge($constants, array_filter($this->constants($parent), static fn (ConstantApi $constant): bool => 'private' !== $constant->visibility));
        }

        return array_merge($constants, $class->constants);
    }

    /**
     * @return array<string, string> @method tags, including the ones of parent interfaces and classes
     */
    public function methodTags(ClassApi $class): array
    {
        $tags = [];
        foreach ([...$class->interfaces, ...(null !== $class->parent ? [$class->parent] : [])] as $name) {
            if (null !== $parent = $this->get($name)) {
                $tags = array_merge($tags, $this->methodTags($parent));
            }
        }

        return array_merge($tags, $class->methodTags);
    }

    /**
     * Lowercase names of all parent classes and interfaces, including those outside the monorepo
     * as far as they are named by monorepo classes.
     *
     * @return list<string>
     */
    public function ancestors(string $name): array
    {
        $key = strtolower(ltrim($name, '\\'));
        if (isset($this->ancestors[$key])) {
            return $this->ancestors[$key];
        }
        $this->ancestors[$key] = [];

        $class = $this->get($name);
        if (null === $class) {
            return [];
        }

        $ancestors = [];
        foreach ([...(null !== $class->parent ? [$class->parent] : []), ...$class->interfaces] as $parent) {
            $ancestors[] = strtolower($parent);
            array_push($ancestors, ...$this->ancestors($parent));
        }

        return $this->ancestors[$key] = array_values(array_unique($ancestors));
    }

    /**
     * Whether every value of type $sub is also a value of type $super.
     */
    public function isSubtype(string $sub, string $super): bool
    {
        $superMembers = self::members($super);
        if (\in_array('mixed', $superMembers, true)) {
            return true;
        }

        foreach (self::members($sub) as $member) {
            if (!$this->isMemberSubtype($member, $superMembers)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function members(string $type): array
    {
        $members = [];
        $depth = 0;
        $current = '';
        foreach (str_split($type) as $char) {
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            }
            if ('|' === $char && 0 === $depth) {
                $members[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $members[] = $current;

        return $members;
    }

    /**
     * @param list<string> $superMembers
     */
    private function isMemberSubtype(string $member, array $superMembers): bool
    {
        $lower = strtolower($member);
        foreach ($superMembers as $super) {
            $superLower = strtolower($super);
            if ($lower === $superLower) {
                return true;
            }
            if ('bool' === $superLower && \in_array($lower, ['true', 'false'], true)) {
                return true;
            }
            if ('iterable' === $superLower && ('array' === $lower || 'traversable' === $lower || \in_array('traversable', $this->ancestors($member), true))) {
                return true;
            }
            if (self::isClassName($member) && ('object' === $superLower || \in_array($superLower, $this->ancestors($member), true))) {
                return true;
            }
        }

        return false;
    }

    private static function isClassName(string $type): bool
    {
        return !\in_array(strtolower($type), ['int', 'float', 'string', 'bool', 'true', 'false', 'null', 'array', 'iterable', 'callable', 'object', 'mixed', 'void', 'never', 'static', 'self', 'parent'], true)
            && !str_starts_with($type, '(');
    }
}
