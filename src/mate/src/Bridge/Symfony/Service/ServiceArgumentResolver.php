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
 * Puts parameter names on the container's positional arguments, and redacts the scalars
 * whose name says they carry a secret.
 *
 * The dump records positions, not names — `convertParameters()` never had them to write.
 * The names live on the constructor (or factory method) signature, so they come from
 * reflection, and a class that cannot be reflected leaves the position unidentified.
 *
 * When a position is unidentified, its scalar is redacted. That is the deliberate direction
 * to fail in: a service ID is wiring and useless to an attacker, but a literal could be an
 * API key, and "we could not tell" has to read as "do not show it". Structural arguments —
 * service references, collections of them, tagged iterators — are never redacted, because
 * they are the wiring the tool exists to reveal.
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
     * Case-insensitive substring match against the parameter name. Kept local on purpose:
     * every formatter in this bridge carries its own list, and hoisting them into a shared
     * utility is a separate change from this one.
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

        // Only literals can leak. A service id, or the tag a tagged iterator collects, is
        // exactly the information this tool exists to surface.
        if ('scalar' === $argument['type'] && true === $argument['literal'] && $sensitive) {
            return ['name' => $name, 'type' => 'scalar', 'value' => self::REDACTED];
        }

        return ['name' => $name, 'type' => $argument['type'], 'value' => $argument['value']];
    }

    /**
     * Nested entries inherit the sensitivity of the parameter they sit under: once the
     * enclosing parameter is a secret (or could not be identified), nothing inside it can
     * be shown either. A keyed entry can additionally be sensitive on its own name.
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
     * @return list<string>|null null when the signature cannot be read at all, which makes every position unidentified
     */
    private function parameterNames(array $constructor, int $argumentCount): ?array
    {
        [$class, $method] = $constructor;

        // A factory declared as `<factory service="..." method="..."/>` carries no class, so
        // there is nothing to reflect and every argument stays unidentified.
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
            // A class that exists but cannot be reflected (unloadable parent, broken
            // autoloader) is the same situation as one that does not exist.
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
