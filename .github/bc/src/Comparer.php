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
 * Classifies the differences between two versions of the API along the Symfony BC promise.
 *
 * @see https://symfony.com/doc/current/contributing/code/bc.html
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Comparer
{
    /** @var list<Finding> */
    private array $findings = [];

    public function __construct(
        private readonly Api $base,
        private readonly Api $head,
        private readonly string $upcomingVersion,
    ) {
    }

    /**
     * @return list<Finding>
     */
    public function compare(): array
    {
        $this->findings = [];

        foreach ($this->base->classes as $key => $base) {
            if (!$base->isCovered()) {
                continue;
            }

            $head = $this->head->classes[$key] ?? null;
            if (null === $head) {
                $this->classRemoved($base);
                continue;
            }

            if (!$head->isCovered()) {
                if ($base->announcedInternal && $head->internal) {
                    $this->add(Finding::MAJOR_ONLY, $head->name, \sprintf('The %s announced with "@internal since" is now @internal.', $base->kind), $head, $head->line, Guide::INTERNAL);
                } else {
                    $this->add(Finding::BREAK, $head->name, \sprintf('The %s is now %s, so it is no longer covered by the BC promise. Announce it with "@internal since Symfony AI %s" first.', $base->kind, $head->internal ? '@internal' : '@experimental', $this->upcomingVersion), $head, $head->line, Guide::INTERNAL);
                }
                continue;
            }

            if (!$base->announcedInternal && $head->announcedInternal) {
                $this->add(Finding::DEPRECATION, $head->name, \sprintf('The %s is announced to become @internal.', $head->kind), $head, $head->line, Guide::INTERNAL);
            }

            $this->compareClass($base, $head);
        }

        foreach ($this->head->classes as $key => $head) {
            if (!isset($this->base->classes[$key]) && null !== $head->deprecated && $head->isCovered()) {
                $this->add(Finding::VIOLATION, $head->name, \sprintf('A new %s cannot be deprecated. Add the replacement instead, and deprecate existing code only.', $head->kind), $head, $head->line, Guide::DEPRECATE);
            }
        }

        return $this->findings;
    }

    private function classRemoved(ClassApi $base): void
    {
        if (null !== $base->deprecated) {
            $this->add(Finding::MAJOR_ONLY, $base->name, \sprintf('The deprecated %s is removed.', $base->kind), $base, $base->line, Guide::DEPRECATE);

            return;
        }

        if (null !== $candidate = $this->movedTo($base)) {
            $this->add(Finding::BREAK, $base->name, \sprintf('The %s is removed. If it moved to "%s", keep the old name working with class_alias() and deprecate it.', $base->kind, $candidate->name), $base, $base->line, Guide::MOVE_CLASS);

            return;
        }

        $this->add(Finding::BREAK, $base->name, \sprintf('The %s is removed. Deprecate it in a minor version and remove it in the next major one.', $base->kind), $base, $base->line, Guide::DEPRECATE);
    }

    /**
     * The new class sharing the most trailing namespace segments with a removed one, if that is unambiguous.
     */
    private function movedTo(ClassApi $removed): ?ClassApi
    {
        $segments = array_reverse(explode('\\', strtolower($removed->name)));
        $best = [];
        $bestScore = 0;
        foreach ($this->head->classes as $key => $candidate) {
            if (isset($this->base->classes[$key]) || $candidate->kind !== $removed->kind) {
                continue;
            }

            $score = 0;
            foreach (array_reverse(explode('\\', $key)) as $i => $segment) {
                if ($segment !== ($segments[$i] ?? null)) {
                    break;
                }
                ++$score;
            }

            if (0 === $score || $score < $bestScore) {
                continue;
            }
            if ($score > $bestScore) {
                $best = [];
                $bestScore = $score;
            }
            $best[] = $candidate;
        }

        return 1 === \count($best) ? $best[0] : null;
    }

    private function compareClass(ClassApi $base, ClassApi $head): void
    {
        if ($base->kind !== $head->kind) {
            $this->add(Finding::BREAK, $head->name, \sprintf('The %s is now a %s.', $base->kind, $head->kind), $head, $head->line, Guide::NO_UPGRADE_PATH);

            return;
        }

        if (!$base->isFinal() && $head->isFinal()) {
            if (null !== $base->finalTag) {
                $this->add(Finding::MAJOR_ONLY, $head->name, 'The class announced with "@final since" is now final.', $head, $head->line, Guide::FINAL);
            } else {
                $this->add(Finding::BREAK, $head->name, 'The class is now final. Announce it with "@final since Symfony AI '.$this->upcomingVersion.'" first.', $head, $head->line, Guide::FINAL);
            }
        } elseif (null === $base->finalTag && null !== $head->finalTag && !$head->isFinal()) {
            $this->add(Finding::DEPRECATION, $head->name, 'The class is announced to become final.', $head, $head->line, Guide::FINAL);
            $this->checkSince($head->finalTag, $head->name, $head, $head->line, Guide::FINAL);
        }

        if ('class' === $base->kind && !$base->abstract && $head->abstract) {
            $this->add(Finding::BREAK, $head->name, 'The class is now abstract.', $head, $head->line, Guide::NO_UPGRADE_PATH);
        }

        if ($base->isExtensible() && !$base->readonly && $head->readonly) {
            $this->add(Finding::BREAK, $head->name, 'The class is now readonly, which breaks child classes with mutable properties.', $head, $head->line, Guide::NO_UPGRADE_PATH);
        }

        if (null !== $base->parent && !\in_array(strtolower($base->parent), $this->head->ancestors($head->name), true)) {
            $this->add(Finding::BREAK, $head->name, \sprintf('The class no longer extends "%s".', $base->parent), $head, $head->line, Guide::NO_UPGRADE_PATH);
        }

        $headAncestors = $this->head->ancestors($head->name);
        foreach ($this->base->ancestors($base->name) as $ancestor) {
            $ancestorApi = $this->base->get($ancestor);
            if (null !== $ancestorApi && 'interface' === $ancestorApi->kind && !\in_array($ancestor, $headAncestors, true) && $ancestorApi->isCovered()) {
                $this->add(Finding::BREAK, $head->name, \sprintf('The %s no longer implements "%s".', $base->kind, $ancestorApi->name), $head, $head->line, Guide::NO_UPGRADE_PATH);
            }
        }

        if (null === $base->deprecated && null !== $head->deprecated) {
            $this->add(Finding::DEPRECATION, $head->name, \sprintf('The %s is deprecated.', $head->kind), $head, $head->line, Guide::DEPRECATE);
            $this->checkSince($head->deprecated, $head->name, $head, $head->line, Guide::DEPRECATE);
        }

        foreach (array_diff($base->cases, $head->cases) as $case) {
            $this->add(Finding::BREAK, $head->name.'::'.$case, 'The enum case is removed.', $head, $head->line, Guide::NO_UPGRADE_PATH);
        }

        $this->compareConstants($base, $head);
        $this->compareProperties($base, $head);
        $this->compareMethods($base, $head);
    }

    private function compareConstants(ClassApi $base, ClassApi $head): void
    {
        $headConstants = $this->head->constants($head);
        foreach ($base->constants as $name => $constant) {
            if ($constant->internal || !SnapshotRenderer::isVisible($constant->visibility, $base->isExtensible(), 'trait' === $base->kind)) {
                continue;
            }

            $symbol = $head->name.'::'.$name;
            $headConstant = $headConstants[$name] ?? null;
            if (null === $headConstant) {
                $this->removed($symbol, 'constant', null !== $constant->deprecated, $head, $head->line);
                continue;
            }

            $line = $head->constants[$name]->line ?? $head->line;
            if (self::visibilityRank($headConstant->visibility) < self::visibilityRank($constant->visibility)) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The constant is now %s.', $headConstant->visibility), $head, $line, Guide::NO_UPGRADE_PATH);
            }
            if ($base->isExtensible() && !$constant->final && $headConstant->final) {
                $this->add(Finding::BREAK, $symbol, 'The constant is now final.', $head, $line, Guide::FINAL);
            }
            if (null === $constant->deprecated && null !== $headConstant->deprecated) {
                $this->add(Finding::DEPRECATION, $symbol, 'The constant is deprecated.', $head, $line, Guide::DEPRECATE);
                $this->checkSince($headConstant->deprecated, $symbol, $head, $line, Guide::DEPRECATE);
            }
        }
    }

    private function compareProperties(ClassApi $base, ClassApi $head): void
    {
        $extensible = $base->isExtensible();
        $headProperties = $this->head->properties($head);
        foreach ($base->properties as $name => $property) {
            if ($property->internal || !SnapshotRenderer::isVisible($property->visibility, $extensible, 'trait' === $base->kind)) {
                continue;
            }

            $symbol = $head->name.'::$'.$name;
            $headProperty = $headProperties[$name] ?? null;
            if (null === $headProperty) {
                $this->removed($symbol, 'property', null !== $property->deprecated, $head, $head->line);
                continue;
            }

            $line = $head->properties[$name]->line ?? $head->line;
            $rank = self::visibilityRank($headProperty->visibility);
            if ($rank < self::visibilityRank($property->visibility)) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The property is now %s.', $headProperty->visibility), $head, $line, Guide::NO_UPGRADE_PATH);
            } elseif ($extensible && 'protected' === $property->visibility && 'public' === $headProperty->visibility) {
                $this->add(Finding::BREAK, $symbol, 'The protected property is now public, which breaks child classes that redeclare it.', $head, $line, Guide::NO_UPGRADE_PATH);
            }
            if ($property->type !== $headProperty->type) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The type of the property changed from "%s" to "%s".', $property->type ?? 'none', $headProperty->type ?? 'none'), $head, $line, Guide::NO_UPGRADE_PATH);
            }
            if ($property->static !== $headProperty->static) {
                $this->add(Finding::BREAK, $symbol, $headProperty->static ? 'The property is now static.' : 'The property is no longer static.', $head, $line, Guide::NO_UPGRADE_PATH);
            }
            if (!$property->readonly && $headProperty->readonly) {
                $this->add(Finding::BREAK, $symbol, 'The property is now readonly.', $head, $line, Guide::NO_UPGRADE_PATH);
            } elseif ($extensible && $property->readonly && !$headProperty->readonly) {
                $this->add(Finding::BREAK, $symbol, 'The property is no longer readonly, which breaks child classes that redeclare it.', $head, $line, Guide::NO_UPGRADE_PATH);
            }
            if (null === $property->deprecated && null !== $headProperty->deprecated) {
                $this->add(Finding::DEPRECATION, $symbol, 'The property is deprecated.', $head, $line, Guide::DEPRECATE);
                $this->checkSince($headProperty->deprecated, $symbol, $head, $line, Guide::DEPRECATE);
            }
        }
    }

    private function compareMethods(ClassApi $base, ClassApi $head): void
    {
        $baseMethods = $this->base->methods($base);
        $headMethods = $this->head->methods($head);
        $baseTags = $this->base->methodTags($base);

        foreach ($baseMethods as $key => $method) {
            if (!$this->isOwnedBy($method, $base, $this->base) || $method->internal || !SnapshotRenderer::isVisible($method->visibility, $base->isExtensible(), 'trait' === $base->kind)) {
                continue;
            }

            $symbol = $head->name.'::'.$method->name.'()';
            $headMethod = $headMethods[$key] ?? null;
            if (null === $headMethod) {
                if (isset($head->methodTags[$key])) {
                    $this->add(Finding::BREAK, $symbol, 'The method is only declared with @method now. Keep the real method until the next major version.', $head, $head->line, Guide::DEPRECATE);
                } else {
                    $this->removed($symbol, 'method', null !== $method->deprecated, $head, $head->line);
                }
                continue;
            }

            if ($headMethod->internal) {
                if ($method->announcedInternal) {
                    $this->add(Finding::MAJOR_ONLY, $symbol, 'The method announced with "@internal since" is now @internal.', $head, $headMethod->line, Guide::INTERNAL);
                } else {
                    $this->add(Finding::BREAK, $symbol, 'The method is now @internal, so it is no longer covered by the BC promise. Announce it with "@internal since Symfony AI '.$this->upcomingVersion.'" first.', $head, $headMethod->line, Guide::INTERNAL);
                }
                continue;
            }

            if (!$method->announcedInternal && $headMethod->announcedInternal) {
                $this->add(Finding::DEPRECATION, $symbol, 'The method is announced to become @internal.', $head, $headMethod->line, Guide::INTERNAL);
            }

            $this->compareMethod($base, $head, $method, $headMethod, $symbol);
        }

        foreach ($headMethods as $key => $method) {
            if (isset($baseMethods[$key]) || strtolower($method->class) !== strtolower($head->name) || $method->internal) {
                continue;
            }

            $symbol = $head->name.'::'.$method->name.'()';
            $abstract = 'interface' === $head->kind || $method->abstract;
            if ($abstract && $base->isExtensible()) {
                if (isset($baseTags[$key])) {
                    $this->add(Finding::MAJOR_ONLY, $symbol, 'The method announced with @method is now declared.', $head, $method->line, Guide::ADD_INTERFACE_METHOD);
                } else {
                    $this->add(Finding::BREAK, $symbol, \sprintf('A new abstract method breaks every %s. Announce it with an @method tag instead, and declare it in the next major version.', 'interface' === $head->kind ? 'implementation' : 'child class'), $head, $method->line, Guide::ADD_INTERFACE_METHOD);
                }
            }

            if (null !== $method->deprecated && 'private' !== $method->visibility) {
                $this->add(Finding::VIOLATION, $symbol, 'A new method cannot be deprecated. Add the replacement instead, and deprecate existing code only.', $head, $method->line, Guide::DEPRECATE);
            }
        }

        foreach (array_diff_key($head->methodTags, $baseTags, $baseMethods) as $signature) {
            $this->add(Finding::NOTICE, $head->name.'::'.$signature, 'A new method is announced with @method. Call it only after checking method_exists().', $head, $head->line, Guide::ADD_INTERFACE_METHOD);
        }
    }

    private function compareMethod(ClassApi $baseClass, ClassApi $head, MethodApi $base, MethodApi $headMethod, string $symbol): void
    {
        $line = $headMethod->line;
        $constructor = $base->isConstructor() && 'class' === $baseClass->kind;
        $overridable = !$constructor && $baseClass->isExtensible() && !$base->isFinal();

        $rank = self::visibilityRank($headMethod->visibility);
        if ($rank < self::visibilityRank($base->visibility)) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The method is now %s.', $headMethod->visibility), $head, $line, Guide::NO_UPGRADE_PATH);
        } elseif ($overridable && 'protected' === $base->visibility && 'public' === $headMethod->visibility) {
            $this->add(Finding::BREAK, $symbol, 'The protected method is now public, which breaks child classes that override it as protected.', $head, $line, Guide::NO_UPGRADE_PATH);
        }

        if ($base->static !== $headMethod->static) {
            $this->add(Finding::BREAK, $symbol, $headMethod->static ? 'The method is now static.' : 'The method is no longer static.', $head, $line, Guide::NO_UPGRADE_PATH);
        }

        if ($baseClass->isExtensible() && !$base->isFinal() && $headMethod->isFinal() && 'private' !== $headMethod->visibility) {
            if (null !== $base->finalTag) {
                $this->add(Finding::MAJOR_ONLY, $symbol, 'The method announced with "@final since" is now final.', $head, $line, Guide::FINAL);
            } else {
                $this->add(Finding::BREAK, $symbol, 'The method is now final. Announce it with "@final since Symfony AI '.$this->upcomingVersion.'" first.', $head, $line, Guide::FINAL);
            }
        } elseif (null === $base->finalTag && null !== $headMethod->finalTag && !$headMethod->isFinal()) {
            $this->add(Finding::DEPRECATION, $symbol, 'The method is announced to become final.', $head, $line, Guide::FINAL);
            $this->checkSince($headMethod->finalTag, $symbol, $head, $line, Guide::FINAL);
        }

        if ('class' === $baseClass->kind && !$base->abstract && $headMethod->abstract) {
            $this->add(Finding::BREAK, $symbol, 'The method is now abstract.', $head, $line, Guide::NO_UPGRADE_PATH);
        }

        if (null === $base->deprecated && null !== $headMethod->deprecated) {
            $this->add(Finding::DEPRECATION, $symbol, 'The method is deprecated.', $head, $line, Guide::DEPRECATE);
            $this->checkSince($headMethod->deprecated, $symbol, $head, $line, Guide::DEPRECATE);
        }

        $this->compareParameters($baseClass, $head, $base, $headMethod, $symbol, $constructor, $overridable);
        $this->compareReturnType($head, $base, $headMethod, $symbol, $overridable);
    }

    private function compareParameters(ClassApi $baseClass, ClassApi $head, MethodApi $base, MethodApi $headMethod, string $symbol, bool $constructor, bool $overridable): void
    {
        $line = $headMethod->line;
        $baseVirtual = array_keys($base->virtualParameters);
        $baseNames = array_map(static fn (ParameterApi $parameter): string => $parameter->name, $base->parameters);
        $headNames = array_map(static fn (ParameterApi $parameter): string => $parameter->name, $headMethod->parameters);

        $shifted = false;
        foreach ($baseNames as $i => $name) {
            $position = array_search($name, $headNames, true);
            if (false !== $position && $position !== $i) {
                $shifted = true;
            }
        }

        if ($shifted) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The arguments moved from (%s) to (%s), which breaks positional calls. Only append new arguments.', self::names($baseNames), self::names($headNames)), $head, $line, Guide::VIRTUAL_ARGUMENT);

            $headByName = array_combine($headNames, $headMethod->parameters);
            foreach ($base->parameters as $parameter) {
                if (isset($headByName[$parameter->name])) {
                    $this->compareParameter($baseClass, $head, $base, $parameter, $headByName[$parameter->name], $symbol, $constructor, $overridable);
                }
            }

            return;
        }

        foreach ($base->parameters as $i => $parameter) {
            $headParameter = $headMethod->parameters[$i] ?? null;

            if (null === $headParameter) {
                if (!$parameter->isOptional()) {
                    $this->add(Finding::BREAK, $symbol, \sprintf('The argument $%s is removed.', $parameter->name), $head, $line, Guide::NO_UPGRADE_PATH);
                }
                continue;
            }

            $this->compareParameter($baseClass, $head, $base, $parameter, $headParameter, $symbol, $constructor, $overridable);
        }

        foreach (\array_slice($headMethod->parameters, \count($base->parameters)) as $offset => $parameter) {
            $virtual = ($baseVirtual[$offset] ?? null) === $parameter->name;
            $name = '$'.$parameter->name;

            if ($virtual) {
                $this->add(Finding::MAJOR_ONLY, $symbol, \sprintf('The virtual argument %s is now declared.', $name), $head, $line, Guide::VIRTUAL_ARGUMENT);
            } elseif (!$parameter->isOptional()) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The required argument %s is added. Add it as a virtual argument first.', $name), $head, $line, Guide::VIRTUAL_ARGUMENT);
            } elseif ($overridable) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The argument %s is added to a method that can be overridden. Add it as a virtual argument first.', $name), $head, $line, Guide::VIRTUAL_ARGUMENT);
            }
        }

        foreach (array_diff(array_keys($headMethod->virtualParameters), $baseVirtual, $headNames) as $name) {
            $this->add(Finding::DEPRECATION, $symbol, \sprintf('The virtual argument $%s is announced.', $name), $head, $line, Guide::VIRTUAL_ARGUMENT);
        }
    }

    private function compareParameter(ClassApi $baseClass, ClassApi $head, MethodApi $baseMethod, ParameterApi $parameter, ParameterApi $headParameter, string $symbol, bool $constructor, bool $overridable): void
    {
        $line = $head->methods[strtolower($baseMethod->name)]->line ?? $head->line;
        $name = '$'.$parameter->name;

        if ($parameter->name !== $headParameter->name) {
            if ($constructor && $baseClass->attribute) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The argument %s of an attribute is renamed to $%s, and attributes are used with named arguments.', $name, $headParameter->name), $head, $line, Guide::NO_UPGRADE_PATH);
            } else {
                $this->add(Finding::NOTICE, $symbol, \sprintf('The argument %s is renamed to $%s, which breaks calls with named arguments.', $name, $headParameter->name), $head, $line, Guide::NO_UPGRADE_PATH);
            }
        }

        if ($parameter->byRef !== $headParameter->byRef || $parameter->variadic !== $headParameter->variadic) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The argument %s is %s.', $name, $parameter->byRef !== $headParameter->byRef ? ($headParameter->byRef ? 'now passed by reference' : 'no longer passed by reference') : ($headParameter->variadic ? 'now variadic' : 'no longer variadic')), $head, $line, Guide::NO_UPGRADE_PATH);
        }

        $default = $this->resolveDefault($parameter->default, $baseMethod->class, $this->base);
        $headDefault = $this->resolveDefault($headParameter->default, $head->name, $this->head);
        if (null !== $default && null === $headDefault && !$headParameter->variadic) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The argument %s no longer has a default value.', $name), $head, $line, Guide::NO_UPGRADE_PATH);
        } elseif (null === $default && null !== $headDefault && $overridable) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The argument %s now has a default value, which breaks implementations that require it.', $name), $head, $line, Guide::NO_UPGRADE_PATH);
        } elseif (null !== $default && null !== $headDefault && $default !== $headDefault) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The default value of %s changed from %s to %s.', $name, $parameter->default, $headParameter->default), $head, $line, Guide::DEFAULT_VALUE);
        }

        $this->compareParameterType($head, $parameter, $headParameter, $symbol, $line, $constructor, $overridable);
    }

    /**
     * Replaces a reference to a class constant by its value, so moving a default into a constant is no change.
     */
    private function resolveDefault(?string $default, string $class, Api $api): ?string
    {
        if (null === $default || 1 !== preg_match('/^\\\\?(self|static|[\w\\\\]+)::(\w+)$/', $default, $match)) {
            return $default;
        }

        $owner = \in_array(strtolower($match[1]), ['self', 'static'], true) ? $api->get($class) : $api->get($match[1]);
        if (null === $owner) {
            return $default;
        }

        return $api->constants($owner)[$match[2]]->value ?? $default;
    }

    /**
     * @param list<string> $names
     */
    private static function names(array $names): string
    {
        return implode(', ', array_map(static fn (string $name): string => '$'.$name, $names));
    }

    private function compareParameterType(ClassApi $head, ParameterApi $base, ParameterApi $headParameter, string $symbol, int $line, bool $constructor, bool $overridable): void
    {
        $from = $base->type;
        $to = $headParameter->type;
        if ($from === $to) {
            return;
        }

        $name = '$'.$base->name;
        if (null === $from) {
            if ('mixed' !== $to) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The argument %s now has the type "%s".', $name, $to), $head, $line, Guide::NARROW_TYPE);
            }

            return;
        }

        $widened = null === $to || $this->widens($from, $to);
        if (!$widened) {
            $change = $this->widens($to, $from) ? 'narrowed' : 'changed';
            $this->add(Finding::BREAK, $symbol, \sprintf('The type of the argument %s is %s from "%s" to "%s". Keep accepting "%s" and deprecate it.', $name, $change, $from, $to, $from), $head, $line, Guide::NARROW_TYPE);
        } elseif ($overridable) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The type of the argument %s is widened from "%s" to "%s", which breaks implementations that declare the old type.', $name, $from, $to ?? 'none'), $head, $line, Guide::NO_UPGRADE_PATH);
        } elseif (!$constructor && null === $to) {
            $this->add(Finding::NOTICE, $symbol, \sprintf('The argument %s lost its type.', $name), $head, $line, Guide::NO_UPGRADE_PATH);
        }
    }

    private function compareReturnType(ClassApi $head, MethodApi $base, MethodApi $headMethod, string $symbol, bool $overridable): void
    {
        $from = $base->returnType;
        $to = $headMethod->returnType;
        if ($from === $to || $base->isConstructor()) {
            return;
        }

        $line = $headMethod->line;
        if (null === $from) {
            if ($overridable) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The return type "%s" is added to a method that can be overridden. Declare it with @return first.', $to), $head, $line, Guide::RETURN_TYPE);
            }

            return;
        }

        if (null === $to) {
            if ('void' !== $from) {
                $this->add(Finding::BREAK, $symbol, \sprintf('The return type "%s" is removed.', $from), $head, $line, Guide::NO_UPGRADE_PATH);
            }

            return;
        }

        if ($overridable || !$this->widens($to, $from)) {
            $this->add(Finding::BREAK, $symbol, \sprintf('The return type changed from "%s" to "%s".', $from, $to), $head, $line, $overridable ? Guide::RETURN_TYPE : Guide::NO_UPGRADE_PATH);
        }
    }

    /**
     * Whether $to accepts every value $from accepts.
     */
    private function widens(string $from, string $to): bool
    {
        return $this->head->isSubtype($from, $to);
    }

    private function removed(string $symbol, string $what, bool $deprecated, ClassApi $head, int $line): void
    {
        if ($deprecated) {
            $this->add(Finding::MAJOR_ONLY, $symbol, \sprintf('The deprecated %s is removed.', $what), $head, $line, Guide::DEPRECATE);
        } else {
            $this->add(Finding::BREAK, $symbol, \sprintf('The %s is removed. Deprecate it in a minor version and remove it in the next major one.', $what), $head, $line, Guide::DEPRECATE);
        }
    }

    /**
     * Methods inherited from another covered class or interface are compared there, once.
     */
    private function isOwnedBy(MethodApi $method, ClassApi $class, Api $api): bool
    {
        if (strtolower($method->class) === strtolower($class->name)) {
            return true;
        }

        $declaring = $api->get($method->class);

        return null !== $declaring && !$declaring->isCovered();
    }

    private function checkSince(string $description, string $symbol, ClassApi $class, int $line, string $guide): void
    {
        $expected = 'since Symfony AI '.$this->upcomingVersion;
        if (1 === preg_match('/^since\s+\S.*?(\d+\.\d+)/', $description, $match) && $match[1] === $this->upcomingVersion) {
            return;
        }

        $this->add(Finding::VIOLATION, $symbol, \sprintf('New deprecations and @final tags must start with "%s", the upcoming version.', $expected), $class, $line, $guide);
    }

    private static function visibilityRank(string $visibility): int
    {
        return match ($visibility) {
            'public' => 2,
            'protected' => 1,
            default => 0,
        };
    }

    /**
     * @param Finding::* $type
     */
    private function add(string $type, string $symbol, string $message, ClassApi $class, int $line, string $guide): void
    {
        $this->findings[] = new Finding($type, $symbol, $message, $class->file, $line, $guide);
    }
}
