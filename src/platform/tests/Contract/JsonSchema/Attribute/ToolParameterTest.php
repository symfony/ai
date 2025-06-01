<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(With::class)]
final class ToolParameterTest extends TestCase
{
    #[Test]
    public function validEnum(): void
    {
        $enum = ['value1', 'value2'];
        $toolParameter = new With(enum: $enum);
        self::assertSame($enum, $toolParameter->enum);
    }

    #[Test]
    public function invalidEnumContainsNonString(): void
    {
        self::expectException(InvalidArgumentException::class);
        $enum = ['value1', 2];
        new With(enum: $enum);
    }

    #[Test]
    public function validConstString(): void
    {
        $const = 'constant value';
        $toolParameter = new With(const: $const);
        self::assertSame($const, $toolParameter->const);
    }

    #[Test]
    public function invalidConstEmptyString(): void
    {
        self::expectException(InvalidArgumentException::class);
        $const = '   ';
        new With(const: $const);
    }

    #[Test]
    public function validPattern(): void
    {
        $pattern = '/^[a-z]+$/';
        $toolParameter = new With(pattern: $pattern);
        self::assertSame($pattern, $toolParameter->pattern);
    }

    #[Test]
    public function invalidPatternEmptyString(): void
    {
        self::expectException(InvalidArgumentException::class);
        $pattern = '   ';
        new With(pattern: $pattern);
    }

    #[Test]
    public function validMinLength(): void
    {
        $minLength = 5;
        $toolParameter = new With(minLength: $minLength);
        self::assertSame($minLength, $toolParameter->minLength);
    }

    #[Test]
    public function invalidMinLengthNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(minLength: -1);
    }

    #[Test]
    public function validMinLengthAndMaxLength(): void
    {
        $minLength = 5;
        $maxLength = 10;
        $toolParameter = new With(minLength: $minLength, maxLength: $maxLength);
        self::assertSame($minLength, $toolParameter->minLength);
        self::assertSame($maxLength, $toolParameter->maxLength);
    }

    #[Test]
    public function invalidMaxLengthLessThanMinLength(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(minLength: 10, maxLength: 5);
    }

    #[Test]
    public function validMinimum(): void
    {
        $minimum = 0;
        $toolParameter = new With(minimum: $minimum);
        self::assertSame($minimum, $toolParameter->minimum);
    }

    #[Test]
    public function invalidMinimumNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(minimum: -1);
    }

    #[Test]
    public function validMultipleOf(): void
    {
        $multipleOf = 5;
        $toolParameter = new With(multipleOf: $multipleOf);
        self::assertSame($multipleOf, $toolParameter->multipleOf);
    }

    #[Test]
    public function invalidMultipleOfNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(multipleOf: -5);
    }

    #[Test]
    public function validExclusiveMinimumAndMaximum(): void
    {
        $exclusiveMinimum = 1;
        $exclusiveMaximum = 10;
        $toolParameter = new With(exclusiveMinimum: $exclusiveMinimum, exclusiveMaximum: $exclusiveMaximum);
        self::assertSame($exclusiveMinimum, $toolParameter->exclusiveMinimum);
        self::assertSame($exclusiveMaximum, $toolParameter->exclusiveMaximum);
    }

    #[Test]
    public function invalidExclusiveMaximumLessThanExclusiveMinimum(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(exclusiveMinimum: 10, exclusiveMaximum: 5);
    }

    #[Test]
    public function validMinItemsAndMaxItems(): void
    {
        $minItems = 1;
        $maxItems = 5;
        $toolParameter = new With(minItems: $minItems, maxItems: $maxItems);
        self::assertSame($minItems, $toolParameter->minItems);
        self::assertSame($maxItems, $toolParameter->maxItems);
    }

    #[Test]
    public function invalidMaxItemsLessThanMinItems(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(minItems: 5, maxItems: 1);
    }

    #[Test]
    public function validUniqueItemsTrue(): void
    {
        $toolParameter = new With(uniqueItems: true);
        self::assertTrue($toolParameter->uniqueItems);
    }

    #[Test]
    public function invalidUniqueItemsFalse(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(uniqueItems: false);
    }

    #[Test]
    public function validMinContainsAndMaxContains(): void
    {
        $minContains = 1;
        $maxContains = 3;
        $toolParameter = new With(minContains: $minContains, maxContains: $maxContains);
        self::assertSame($minContains, $toolParameter->minContains);
        self::assertSame($maxContains, $toolParameter->maxContains);
    }

    #[Test]
    public function invalidMaxContainsLessThanMinContains(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(minContains: 3, maxContains: 1);
    }

    #[Test]
    public function validRequired(): void
    {
        $toolParameter = new With(required: true);
        self::assertTrue($toolParameter->required);
    }

    #[Test]
    public function validMinPropertiesAndMaxProperties(): void
    {
        $minProperties = 1;
        $maxProperties = 5;
        $toolParameter = new With(minProperties: $minProperties, maxProperties: $maxProperties);
        self::assertSame($minProperties, $toolParameter->minProperties);
        self::assertSame($maxProperties, $toolParameter->maxProperties);
    }

    #[Test]
    public function invalidMaxPropertiesLessThanMinProperties(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(minProperties: 5, maxProperties: 1);
    }

    #[Test]
    public function validDependentRequired(): void
    {
        $toolParameter = new With(dependentRequired: true);
        self::assertTrue($toolParameter->dependentRequired);
    }

    #[Test]
    public function validCombination(): void
    {
        $toolParameter = new With(
            enum: ['value1', 'value2'],
            const: 'constant',
            pattern: '/^[a-z]+$/',
            minLength: 5,
            maxLength: 10,
            minimum: 0,
            maximum: 100,
            multipleOf: 5,
            exclusiveMinimum: 1,
            exclusiveMaximum: 99,
            minItems: 1,
            maxItems: 10,
            uniqueItems: true,
            minContains: 1,
            maxContains: 5,
            required: true,
            minProperties: 1,
            maxProperties: 5,
            dependentRequired: true
        );

        self::assertInstanceOf(With::class, $toolParameter);
    }

    #[Test]
    public function invalidCombination(): void
    {
        self::expectException(InvalidArgumentException::class);
        new With(minLength: -1, maxLength: -2);
    }
}
