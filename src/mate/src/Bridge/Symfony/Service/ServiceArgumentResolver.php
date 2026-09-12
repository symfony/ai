<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Service;

use Symfony\AI\Mate\Bridge\Symfony\Model\ServiceDefinition;

/**
 * Puts parameter names on the container's positional arguments (read from the dump by
 * position only), and redacts scalars whose name looks like a secret or whose position
 * could not be identified. Service references, collections and tagged iterators are never
 * redacted: they are the wiring the tool exists to reveal.
 *
 * @phpstan-import-type ParsedArgument from ServiceDefinition
 *
 * @phpstan-type ResolvedArgument array{name: string, type: 'service'|'collection'|'scalar', value: mixed}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ServiceArgumentResolver
{
    /**
     * Case-insensitive substring match against the parameter name.
     *
     * @var list<string>
     */
    private const SENSITIVE_PARAMETER_PATTERNS = [
        'SECRET',
        'KEY',
        'PASSWORD',
        'TOKEN',
        'BEARER',
        'AUTH',
        'CREDENTIAL',
        'PRIVATE',
        'COOKIE',
    ];

    private const REDACTED = '***REDACTED***';

    /**
     * @param array{0: string|null, 1: string} $constructor class and method the arguments are passed to; a factory
     *                                                      makes this the factory method rather than `__construct`
     * @param list<ParsedArgument>             $arguments
     *
     * @return list<ResolvedArgument>
     */
    public function resolve(array $constructor, array $arguments): array
    {
        $names = $this->parameterNames($constructor, \count($arguments));

        $resolved = [];
        foreach ($arguments as $position => $argument) {
            $name = $names[$position] ?? null;

            $resolved[] = $this->resolveArgument(
                $argument,
                $name ?? \sprintf('#%d', $position),
                null === $name || $this->isSensitive($name),
            );
        }

        return $resolved;
    }

    /**
     * @param ParsedArgument $argument
     *
     * @return ResolvedArgument
     */
    private function resolveArgument(array $argument, string $name, bool $sensitive): array
    {
        if ('collection' === $argument['type']) {
            /** @var list<ParsedArgument> $children */
            $children = \is_array($argument['value']) ? $argument['value'] : [];

            return [
                'name' => $name,
                'type' => 'collection',
                'value' => $this->resolveCollection($children, $sensitive),
            ];
        }

        // Only literals can leak; a service id is wiring, not a secret.
        if ('scalar' === $argument['type'] && true === $argument['literal'] && $sensitive) {
            return ['name' => $name, 'type' => 'scalar', 'value' => self::REDACTED];
        }

        return ['name' => $name, 'type' => $argument['type'], 'value' => $argument['value']];
    }

    /**
     * A nested entry inherits its enclosing parameter's sensitivity, and can additionally
     * be sensitive on its own key.
     *
     * @param list<ParsedArgument> $children
     *
     * @return list<ResolvedArgument>
     */
    private function resolveCollection(array $children, bool $sensitive): array
    {
        $resolved = [];
        foreach ($children as $index => $child) {
            $key = $child['key'];

            $resolved[] = $this->resolveArgument(
                $child,
                $key ?? \sprintf('#%d', $index),
                $sensitive || (null !== $key && $this->isSensitive($key)),
            );
        }

        return $resolved;
    }

    /**
     * @param array{0: string|null, 1: string} $constructor
     *
     * @return list<string>|null null means every position is unidentified
     */
    private function parameterNames(array $constructor, int $argumentCount): ?array
    {
        [$class, $method] = $constructor;

        // A factory service (vs. a factory class) carries no class to reflect.
        if (null === $class || '' === $class) {
            return null;
        }

        try {
            if (!class_exists($class)) {
                return null;
            }

            $reflection = new \ReflectionClass($class);
            $function = '__construct' === $method
                ? $reflection->getConstructor()
                : ($reflection->hasMethod($method) ? $reflection->getMethod($method) : null);

            if (null === $function) {
                return null;
            }

            $parameters = $function->getParameters();
        } catch (\Throwable) {
            return null;
        }

        $names = [];
        foreach ($parameters as $parameter) {
            $names[] = $parameter->getName();
        }

        // Everything past a variadic parameter still belongs to it.
        $last = end($parameters);
        if (false !== $last && $last->isVariadic()) {
            while (\count($names) < $argumentCount) {
                $names[] = $last->getName();
            }
        }

        return $names;
    }

    private function isSensitive(string $name): bool
    {
        $upper = strtoupper($name);

        foreach (self::SENSITIVE_PARAMETER_PATTERNS as $pattern) {
            if (str_contains($upper, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
