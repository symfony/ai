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

use Webmozart\Assert\Assert;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class With
{
    /**
     * @param list<int|string>|null    $enum
     * @param string|int|string[]|null $const
     */
    public function __construct(
        // can be used by many types
        public ?array $enum = null,
        public string|int|array|null $const = null,

        // string
        public ?string $pattern = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,

        // integer
        public ?int $minimum = null,
        public ?int $maximum = null,
        public ?int $multipleOf = null,
        public ?int $exclusiveMinimum = null,
        public ?int $exclusiveMaximum = null,

        // array
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,

        // object
        public ?bool $required = null,
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public ?bool $dependentRequired = null,
    ) {
        if (\is_array($enum)) {
            Assert::allString($enum);
        }

        if (\is_string($const)) {
            Assert::stringNotEmpty(trim($const));
        }

        if (\is_string($pattern)) {
            Assert::stringNotEmpty(trim($pattern));
        }

        if (\is_int($minLength)) {
            Assert::greaterThanEq($minLength, 0);

            if (\is_int($maxLength)) {
                Assert::greaterThanEq($maxLength, $minLength);
            }
        }

        if (\is_int($maxLength)) {
            Assert::greaterThanEq($maxLength, 0);
        }

        if (\is_int($minimum)) {
            Assert::greaterThanEq($minimum, 0);

            if (\is_int($maximum)) {
                Assert::greaterThanEq($maximum, $minimum);
            }
        }

        if (\is_int($maximum)) {
            Assert::greaterThanEq($maximum, 0);
        }

        if (\is_int($multipleOf)) {
            Assert::greaterThanEq($multipleOf, 0);
        }

        if (\is_int($exclusiveMinimum)) {
            Assert::greaterThanEq($exclusiveMinimum, 0);

            if (\is_int($exclusiveMaximum)) {
                Assert::greaterThanEq($exclusiveMaximum, $exclusiveMinimum);
            }
        }

        if (\is_int($exclusiveMaximum)) {
            Assert::greaterThanEq($exclusiveMaximum, 0);
        }

        if (\is_int($minItems)) {
            Assert::greaterThanEq($minItems, 0);

            if (\is_int($maxItems)) {
                Assert::greaterThanEq($maxItems, $minItems);
            }
        }

        if (\is_int($maxItems)) {
            Assert::greaterThanEq($maxItems, 0);
        }

        if (\is_bool($uniqueItems)) {
            Assert::true($uniqueItems);
        }

        if (\is_int($minContains)) {
            Assert::greaterThanEq($minContains, 0);

            if (\is_int($maxContains)) {
                Assert::greaterThanEq($maxContains, $minContains);
            }
        }

        if (\is_int($maxContains)) {
            Assert::greaterThanEq($maxContains, 0);
        }

        if (\is_int($minProperties)) {
            Assert::greaterThanEq($minProperties, 0);

            if (\is_int($maxProperties)) {
                Assert::greaterThanEq($maxProperties, $minProperties);
            }
        }

        if (\is_int($maxProperties)) {
            Assert::greaterThanEq($maxProperties, 0);
        }
    }
}
