<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Attribute;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class With
{
    /**
     * @param list<int|float|string|null>|null $enum
     * @param string|int|string[]|null         $const
     * @param string|null                      $ref   A path to external schema file. This is mutually exclusive with all the other arguments.
     */
    public function __construct(
        // can be used by many types
        public readonly ?string $description = null,
        public readonly mixed $example = null,
        public readonly ?array $enum = null,
        public readonly string|int|array|null $const = null,

        // string
        public readonly ?string $pattern = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,

        // number
        public readonly int|float|null $minimum = null,
        public readonly int|float|null $maximum = null,
        public readonly int|float|null $multipleOf = null,
        public readonly int|float|null $exclusiveMinimum = null,
        public readonly int|float|null $exclusiveMaximum = null,

        // array
        public readonly ?int $minItems = null,
        public readonly ?int $maxItems = null,
        public readonly ?bool $uniqueItems = null,
        public readonly ?int $minContains = null,
        public readonly ?int $maxContains = null,

        // object
        public readonly ?int $minProperties = null,
        public readonly ?int $maxProperties = null,
        public readonly ?bool $dependentRequired = null,

        // a reference to a schema file
        public readonly ?string $ref = null,
    ) {
        if ($this->ref) {
            if (\count(array_filter((array) $this, static fn (mixed $value) => null !== $value)) > 1) {
                throw new InvalidArgumentException('When "ref" is defined, no other arguments are allowed.');
            }

            if (!is_readable($this->ref)) {
                throw new InvalidArgumentException(\sprintf('The provided schema file "%s" is not readable', $this->ref));
            }

            return;
        }

        if (\is_array($enum)) {
            /* @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (array_filter($enum, static fn (mixed $item) => null === $item || \is_int($item) || \is_float($item) || \is_string($item)) !== $enum) {
                throw new InvalidArgumentException('All enum values must be float, integer, strings, or null.');
            }
        }

        if (\is_string($description)) {
            if ('' === trim($description)) {
                throw new InvalidArgumentException('Description string must not be empty.');
            }
        }

        if (\is_string($const)) {
            if ('' === trim($const)) {
                throw new InvalidArgumentException('Const string must not be empty.');
            }
        }

        if (\is_string($pattern)) {
            if ('' === trim($pattern)) {
                throw new InvalidArgumentException('Pattern string must not be empty.');
            }
        }

        if (\is_int($minLength)) {
            if ($minLength < 0) {
                throw new InvalidArgumentException('MinLength must be greater than or equal to 0.');
            }

            if (\is_int($maxLength)) {
                if ($maxLength < $minLength) {
                    throw new InvalidArgumentException('MaxLength must be greater than or equal to minLength.');
                }
            }
        }

        if (\is_int($maxLength)) {
            if ($maxLength < 0) {
                throw new InvalidArgumentException('MaxLength must be greater than or equal to 0.');
            }
        }

        if (null !== $minimum && null !== $maximum && $maximum < $minimum) {
            throw new InvalidArgumentException('Maximum must be greater than or equal to minimum.');
        }

        if (null !== $multipleOf && $multipleOf < 0) {
            throw new InvalidArgumentException('MultipleOf must be greater than or equal to 0.');
        }

        if (null !== $exclusiveMinimum && null !== $exclusiveMaximum && $exclusiveMaximum < $exclusiveMinimum) {
            throw new InvalidArgumentException('ExclusiveMaximum must be greater than or equal to exclusiveMinimum.');
        }

        if (\is_int($minItems)) {
            if ($minItems < 0) {
                throw new InvalidArgumentException('MinItems must be greater than or equal to 0.');
            }

            if (\is_int($maxItems)) {
                if ($maxItems < $minItems) {
                    throw new InvalidArgumentException('MaxItems must be greater than or equal to minItems.');
                }
            }
        }

        if (\is_int($maxItems)) {
            if ($maxItems < 0) {
                throw new InvalidArgumentException('MaxItems must be greater than or equal to 0.');
            }
        }

        if (\is_bool($uniqueItems)) {
            if (true !== $uniqueItems) {
                throw new InvalidArgumentException('UniqueItems must be true when specified.');
            }
        }

        if (\is_int($minContains)) {
            if ($minContains < 0) {
                throw new InvalidArgumentException('MinContains must be greater than or equal to 0.');
            }

            if (\is_int($maxContains)) {
                if ($maxContains < $minContains) {
                    throw new InvalidArgumentException('MaxContains must be greater than or equal to minContains.');
                }
            }
        }

        if (\is_int($maxContains)) {
            if ($maxContains < 0) {
                throw new InvalidArgumentException('MaxContains must be greater than or equal to 0.');
            }
        }

        if (\is_int($minProperties)) {
            if ($minProperties < 0) {
                throw new InvalidArgumentException('MinProperties must be greater than or equal to 0.');
            }

            if (\is_int($maxProperties)) {
                if ($maxProperties < $minProperties) {
                    throw new InvalidArgumentException('MaxProperties must be greater than or equal to minProperties.');
                }
            }
        }

        if (\is_int($maxProperties)) {
            if ($maxProperties < 0) {
                throw new InvalidArgumentException('MaxProperties must be greater than or equal to 0.');
            }
        }
    }
}
