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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class ToolParameterTest extends TestCase
{
    public function testValidEnum()
    {
        $enum = ['value1', 'value2'];
        $toolParameter = new Schema(enum: $enum);
        $this->assertSame($enum, $toolParameter->enum);
    }

    public function testInvalidEnumContainsInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        $enum = ['value1', new \stdClass()];
        new Schema(enum: $enum); /* @phpstan-ignore-line argument.type */
    }

    public function testValidConstString()
    {
        $const = 'constant value';
        $toolParameter = new Schema(const: $const);
        $this->assertSame($const, $toolParameter->const);
    }

    public function testInvalidConstEmptyString()
    {
        $this->expectException(InvalidArgumentException::class);
        $const = '   ';
        new Schema(const: $const);
    }

    public function testValidPattern()
    {
        $pattern = '/^[a-z]+$/';
        $toolParameter = new Schema(pattern: $pattern);
        $this->assertSame($pattern, $toolParameter->pattern);
    }

    public function testInvalidPatternEmptyString()
    {
        $this->expectException(InvalidArgumentException::class);
        $pattern = '   ';
        new Schema(pattern: $pattern);
    }

    public function testValidMinLength()
    {
        $minLength = 5;
        $toolParameter = new Schema(minLength: $minLength);
        $this->assertSame($minLength, $toolParameter->minLength);
    }

    public function testInvalidMinLengthNegative()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(minLength: -1);
    }

    public function testValidMinLengthAndMaxLength()
    {
        $minLength = 5;
        $maxLength = 10;
        $toolParameter = new Schema(minLength: $minLength, maxLength: $maxLength);
        $this->assertSame($minLength, $toolParameter->minLength);
        $this->assertSame($maxLength, $toolParameter->maxLength);
    }

    public function testInvalidMaxLengthLessThanMinLength()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(minLength: 10, maxLength: 5);
    }

    #[TestWith([0], 'zero')]
    #[TestWith([1.5], 'positive')]
    #[TestWith([-1], 'negative')]
    public function testValidMinimum(int|float $minimum)
    {
        $toolParameter = new Schema(minimum: $minimum);
        $this->assertSame($minimum, $toolParameter->minimum);
    }

    public function testValidMultipleOf()
    {
        $multipleOf = 5;
        $toolParameter = new Schema(multipleOf: $multipleOf);
        $this->assertSame($multipleOf, $toolParameter->multipleOf);
    }

    public function testInvalidMultipleOfNegative()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(multipleOf: -5);
    }

    public function testValidExclusiveMinimumAndMaximum()
    {
        $exclusiveMinimum = 1;
        $exclusiveMaximum = 10;
        $toolParameter = new Schema(exclusiveMinimum: $exclusiveMinimum, exclusiveMaximum: $exclusiveMaximum);
        $this->assertSame($exclusiveMinimum, $toolParameter->exclusiveMinimum);
        $this->assertSame($exclusiveMaximum, $toolParameter->exclusiveMaximum);
    }

    public function testInvalidExclusiveMaximumLessThanExclusiveMinimum()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(exclusiveMinimum: 10, exclusiveMaximum: 5);
    }

    public function testValidMinItemsAndMaxItems()
    {
        $minItems = 1;
        $maxItems = 5;
        $toolParameter = new Schema(minItems: $minItems, maxItems: $maxItems);
        $this->assertSame($minItems, $toolParameter->minItems);
        $this->assertSame($maxItems, $toolParameter->maxItems);
    }

    public function testInvalidMaxItemsLessThanMinItems()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(minItems: 5, maxItems: 1);
    }

    public function testValidUniqueItemsTrue()
    {
        $toolParameter = new Schema(uniqueItems: true);
        $this->assertTrue($toolParameter->uniqueItems);
    }

    public function testInvalidUniqueItemsFalse()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(uniqueItems: false);
    }

    public function testValidMinContainsAndMaxContains()
    {
        $minContains = 1;
        $maxContains = 3;
        $toolParameter = new Schema(minContains: $minContains, maxContains: $maxContains);
        $this->assertSame($minContains, $toolParameter->minContains);
        $this->assertSame($maxContains, $toolParameter->maxContains);
    }

    public function testInvalidMaxContainsLessThanMinContains()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(minContains: 3, maxContains: 1);
    }

    public function testValidMinPropertiesAndMaxProperties()
    {
        $minProperties = 1;
        $maxProperties = 5;
        $toolParameter = new Schema(minProperties: $minProperties, maxProperties: $maxProperties);
        $this->assertSame($minProperties, $toolParameter->minProperties);
        $this->assertSame($maxProperties, $toolParameter->maxProperties);
    }

    public function testInvalidMaxPropertiesLessThanMinProperties()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(minProperties: 5, maxProperties: 1);
    }

    public function testValidDependentRequired()
    {
        $toolParameter = new Schema(dependentRequired: true);
        $this->assertTrue($toolParameter->dependentRequired);
    }

    public function testValidCombination()
    {
        $toolParameter = new Schema(
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
            minProperties: 1,
            maxProperties: 5,
            dependentRequired: true
        );

        $this->assertInstanceOf(Schema::class, $toolParameter);
    }

    public function testInvalidCombination()
    {
        $this->expectException(InvalidArgumentException::class);
        new Schema(minLength: -1, maxLength: -2);
    }
}
