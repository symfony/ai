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
        $known = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $known[$name] = true;

            if (\array_key_exists($name, $arguments)) {
                if ($parameter->isVariadic()) {
                    foreach ($this->normalizeVariadic($arguments[$name]) as $value) {
                        $finalArgs[] = $this->cast($value, $parameter);
                    }

                    continue;
                }

                $finalArgs[] = $this->cast($arguments[$name], $parameter);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $finalArgs[] = $parameter->getDefaultValue();

                continue;
            }

            if ($parameter->isOptional()) {
                continue;
            }

            throw new InvalidArgumentException(\sprintf('Missing required argument "%s" for "%s::%s()".', $name, $method->class, $method->name));
        }

        // A name the signature does not know is a typo, not an argument. Silently dropping it
        // returns a plausible-looking answer computed from different inputs than were asked for.
        $unknown = array_keys(array_diff_key($arguments, $known));
        if ([] !== $unknown) {
            $hint = [] === $known
                ? ' It takes no arguments.'
                : ' Known: "'.implode('", "', array_keys($known)).'".';

            throw new InvalidArgumentException(\sprintf('Unknown argument "%s" for "%s::%s()".', implode('", "', $unknown), $method->class, $method->name).$hint);
        }

        return $finalArgs;
    }

    /**
     * @return list<mixed>
     */
    private function normalizeVariadic(mixed $argument): array
    {
        if (\is_array($argument)) {
            return array_values($argument);
        }

        return [$argument];
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

        // `true` without an explicit value means a bare `--flag`, which only a boolean can accept.
        // Coercing it to 1 or "true" would answer a question that was never asked.
        if (true === $argument && !\in_array($type->getName(), ['bool', 'mixed'], true)) {
            throw new InvalidArgumentException(\sprintf('The "--%s" option requires a value.', $parameter->getName()));
        }

        $typeName = $type->getName();

        // A list arrives when the same option was repeated. Only a variadic parameter, which
        // build() has already spread, or an array one can absorb that.
        if (\is_array($argument) && !\in_array(strtolower($typeName), ['array', 'iterable', 'mixed'], true)) {
            throw new InvalidArgumentException(\sprintf('The "--%s" option takes a single value, but a list of %d was given.', $parameter->getName(), \count($argument)));
        }

        try {
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
        } catch (InvalidArgumentException $e) {
            // The casters see a bare value; naming the option and the value it came from is what
            // lets a caller correct the call instead of guessing which one was rejected.
            throw new InvalidArgumentException('Invalid value '.$this->describe($argument).\sprintf(' for "--%s": ', $parameter->getName()).$e->getMessage(), 0, $e);
        }
    }

    private function describe(mixed $argument): string
    {
        if (\is_string($argument)) {
            return '"'.$argument.'"';
        }

        if (\is_scalar($argument)) {
            return var_export($argument, true);
        }

        return get_debug_type($argument);
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

            // CLI values always arrive as strings, so coerce to the backing type before tryFrom(),
            // which would otherwise raise a TypeError instead of our message.
            $backingType = (string) (new \ReflectionEnum($typeName))->getBackingType();
            if ('int' === $backingType) {
                if (1 !== preg_match('/^[+-]?\d+$/', (string) $argument)) {
                    throw new InvalidArgumentException(\sprintf('Invalid value "%s" for backed enum "%s".', $argument, $typeName));
                }

                $argument = (int) $argument;
            } elseif (\is_int($argument)) {
                $argument = (string) $argument;
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

        if (\is_string($argument) && 1 === preg_match('/^[+-]?\d+$/', $argument)) {
            return (int) $argument;
        }

        if (is_numeric($argument)) {
            $float = (float) $argument;
            if (is_finite($float) && floor($float) == $float) {
                return (int) $float;
            }
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

        // Guard before the string casts below, which would emit an "array to string" warning.
        if (!\is_scalar($argument)) {
            throw new InvalidArgumentException('Cannot cast value to boolean. Use true/false/1/0.');
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
