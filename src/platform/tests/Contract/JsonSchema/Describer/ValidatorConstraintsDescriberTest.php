<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Describer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ValidatorConstraintsDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ValidatorConstraintsFixture;
use Symfony\Component\Validator\Validation;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class ValidatorConstraintsDescriberTest extends TestCase
{
    /**
     * @param JsonSchema|array<string, mixed>|null $initialSchema
     * @param JsonSchema|array<string, mixed>      $expectedSchema
     */
    #[DataProvider('provideDescribeCases')]
    public function testDescribe(string $property, ?array $initialSchema, array $expectedSchema)
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $describer = new ValidatorConstraintsDescriber($validator);
        $propertyReflection = new \ReflectionProperty(ValidatorConstraintsFixture::class, $property);

        $schema = $initialSchema;
        $describer->describe($propertyReflection, $schema);

        $this->assertSame($expectedSchema, $schema);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<mixed>|null, 2: array<mixed>}>
     */
    public static function provideDescribeCases(): iterable
    {
        yield 'NotBlank string' => ['notBlankString', ['type' => 'string'], ['type' => 'string', 'nullable' => false, 'minLength' => 1]];
        yield 'Blank string' => ['blankString', ['type' => 'string'], ['type' => 'string', 'nullable' => true, 'maxLength' => 0]];
        yield 'Length string' => ['lengthString', null, ['minLength' => 2, 'maxLength' => 4]];
        yield 'Regex string' => ['regexString', null, ['pattern' => '[a-z]+']];
        yield 'Choice string' => ['choiceString', null, ['enum' => ['a', 'b']]];
        yield 'Choice array' => ['choiceArray', ['type' => 'array', 'items' => ['type' => 'string']], ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['x', 'y']], 'minItems' => 1, 'maxItems' => 2]];
        yield 'Choice callback' => ['choiceCallback', ['type' => 'integer'], ['type' => 'integer', 'enum' => [1, 2, 3]]];
        yield 'Count and unique array' => ['countedArray', null, ['minItems' => 2, 'maxItems' => 4, 'uniqueItems' => true]];
        yield 'Numeric range' => ['numberRange', null, ['multipleOf' => 3, 'minimum' => 10, 'exclusiveMinimum' => true, 'maximum' => 100]];
        yield 'Range constraint' => ['rangedNumber', null, ['minimum' => 5, 'maximum' => 15]];
        yield 'Positive' => ['positiveNumber', null, ['minimum' => 0, 'exclusiveMinimum' => true]];
        yield 'Negative or zero' => ['negativeNumber', null, ['maximum' => 0]];
        yield 'EqualTo' => ['equalTo', null, ['enum' => ['foo']]];
        yield 'NotEqualTo' => ['notEqualTo', null, ['not' => ['enum' => ['bar']]]];
        yield 'Email format' => ['email', null, ['format' => 'email']];
        yield 'Url format' => ['url', null, ['format' => 'uri']];
        yield 'Date format' => ['date', null, ['format' => 'date']];
        yield 'DateTime format' => ['dateTime', null, ['format' => 'date-time']];
        yield 'Time pattern' => ['time', null, ['pattern' => '^([01]\d|2[0-3]):[0-5]\d$']];
        yield 'IPv4 format' => ['ipv4', null, ['format' => 'ipv4']];
        yield 'IPv6 format' => ['ipv6', null, ['format' => 'ipv6']];
        yield 'Hostname format' => ['hostname', null, ['format' => 'hostname']];
        yield 'Uuid format' => ['uuid', null, ['format' => 'uuid']];
        yield 'Ulid pattern' => ['ulid', null, ['pattern' => '^[0-7][0-9A-HJKMNP-TV-Z]{25}$']];
        yield 'IsTrue const' => ['mustBeTrue', null, ['const' => true]];
        yield 'IsFalse const' => ['mustBeFalse', null, ['const' => false]];
        yield 'IsNull const' => ['mustBeNull', ['type' => ['string', 'null']], ['type' => ['string', 'null'], 'const' => null]];
        yield 'NotNull nullable false' => ['mustNotBeNull', ['type' => ['string', 'null']], ['type' => ['string', 'null'], 'nullable' => false]];
        yield 'Type constraint narrows schema type' => ['typedByConstraint', ['type' => ['string', 'null', 'integer']], ['type' => ['string', 'null']]];
    }
}
