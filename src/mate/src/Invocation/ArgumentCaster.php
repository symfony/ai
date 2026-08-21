<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Invocation;

use Symfony\AI\Mate\Exception\InvalidArgumentException;

/**
 * Builds an ordered argument list for a handler method from a name-keyed argument bag,
 * coercing scalar values to the parameter types declared by the method signature.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ArgumentCaster
{
    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<mixed>
     */
    public function build(\ReflectionMethod $method, array $arguments): array
    {
        $finalArgs = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (\array_key_exists($name, $arguments)) {
                $finalArgs[] = $this->cast($arguments[$name], $parameter);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $finalArgs[] = $parameter->getDefaultValue();

                continue;
            }

            if ($parameter->allowsNull()) {
                $finalArgs[] = null;

                continue;
            }

            if ($parameter->isOptional()) {
                continue;
            }

            throw new InvalidArgumentException(\sprintf('Missing required argument "%s" for "%s::%s()".', $name, $method->class, $method->name));
        }

        return $finalArgs;
    }

    private function cast(mixed $argument, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if (null === $argument && null !== $type && $type->allowsNull()) {
            return null;
        }

        if (!$type instanceof \ReflectionNamedType) {
            return $argument;
        }

        $typeName = $type->getName();

        if (enum_exists($typeName)) {
            return $this->castEnum($argument, $typeName);
        }

        return match (strtolower($typeName)) {
            'int', 'integer' => $this->castInt($argument),
            'string' => $this->castString($argument),
            'bool', 'boolean' => $this->castBool($argument),
            'float', 'double' => $this->castFloat($argument),
            'array' => $this->castArray($argument),
            default => $argument,
        };
    }

    private function castEnum(mixed $argument, string $typeName): mixed
    {
        if (\is_object($argument) && $argument instanceof $typeName) {
            return $argument;
        }

        if (is_subclass_of($typeName, \BackedEnum::class)) {
            if (!\is_int($argument) && !\is_string($argument)) {
                throw new InvalidArgumentException(\sprintf('Invalid value for backed enum "%s".', $typeName));
            }

            $value = $typeName::tryFrom($argument);
            if (null === $value) {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" for backed enum "%s".', $argument, $typeName));
            }

            return $value;
        }

        if (\is_string($argument)) {
            foreach ($typeName::cases() as $case) {
                if ($case->name === $argument) {
                    return $case;
                }
            }
        }

        throw new InvalidArgumentException(\sprintf('Invalid value for enum "%s".', $typeName));
    }

    private function castInt(mixed $argument): int
    {
        if (\is_int($argument)) {
            return $argument;
        }

        if (\is_bool($argument)) {
            return (int) $argument;
        }

        if (\is_string($argument) && ctype_digit(ltrim($argument, '-'))) {
            return (int) $argument;
        }

        if (is_numeric($argument) && floor((float) $argument) == $argument) {
            return (int) $argument;
        }

        throw new InvalidArgumentException('Cannot cast value to integer.');
    }

    private function castString(mixed $argument): string
    {
        if (\is_string($argument)) {
            return $argument;
        }

        if (\is_int($argument) || \is_float($argument)) {
            return (string) $argument;
        }

        if (\is_bool($argument)) {
            return $argument ? 'true' : 'false';
        }

        throw new InvalidArgumentException('Cannot cast value to string.');
    }

    private function castBool(mixed $argument): bool
    {
        if (\is_bool($argument)) {
            return $argument;
        }

        if (1 === $argument || '1' === $argument || 'true' === strtolower((string) $argument)) {
            return true;
        }

        if (0 === $argument || '0' === $argument || 'false' === strtolower((string) $argument)) {
            return false;
        }

        throw new InvalidArgumentException('Cannot cast value to boolean. Use true/false/1/0.');
    }

    private function castFloat(mixed $argument): float
    {
        if (\is_float($argument) || \is_int($argument)) {
            return (float) $argument;
        }

        if (is_numeric($argument)) {
            return (float) $argument;
        }

        throw new InvalidArgumentException('Cannot cast value to float.');
    }

    /**
     * @return array<mixed>
     */
    private function castArray(mixed $argument): array
    {
        if (\is_array($argument)) {
            return $argument;
        }

        throw new InvalidArgumentException('Cannot cast value to array.');
    }
}
