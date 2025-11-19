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
     */
    public function __construct(
        // can be used by many types
        public readonly ?array $enum = null,
        public readonly string|int|array|null $const = null,

        // string
        public readonly ?string $pattern = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,

        // integer
        public readonly ?int $minimum = null,
        public readonly ?int $maximum = null,
        public readonly ?int $multipleOf = null,
        public readonly ?int $exclusiveMinimum = null,
        public readonly ?int $exclusiveMaximum = null,

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
    ) {
        if (\is_array($enum)) {
            /* @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (array_filter($enum, fn (mixed $item) => null === $item || \is_int($item) || \is_float($item) || \is_string($item)) !== $enum) {
                throw new InvalidArgumentException('All enum values must be float, integer, strings, or null.');
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

        if (\is_int($minimum)) {
            if ($minimum < 0) {
                throw new InvalidArgumentException('Minimum must be greater than or equal to 0.');
            }

            if (\is_int($maximum)) {
                if ($maximum < $minimum) {
                    throw new InvalidArgumentException('Maximum must be greater than or equal to minimum.');
                }
            }
        }

        if (\is_int($maximum)) {
            if ($maximum < 0) {
                throw new InvalidArgumentException('Maximum must be greater than or equal to 0.');
            }
        }

        if (\is_int($multipleOf)) {
            if ($multipleOf < 0) {
                throw new InvalidArgumentException('MultipleOf must be greater than or equal to 0.');
            }
        }

        if (\is_int($exclusiveMinimum)) {
            if ($exclusiveMinimum < 0) {
                throw new InvalidArgumentException('ExclusiveMinimum must be greater than or equal to 0.');
            }

            if (\is_int($exclusiveMaximum)) {
                if ($exclusiveMaximum < $exclusiveMinimum) {
                    throw new InvalidArgumentException('ExclusiveMaximum must be greater than or equal to exclusiveMinimum.');
                }
            }
        }

        if (\is_int($exclusiveMaximum)) {
            if ($exclusiveMaximum < 0) {
                throw new InvalidArgumentException('ExclusiveMaximum must be greater than or equal to 0.');
            }
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
