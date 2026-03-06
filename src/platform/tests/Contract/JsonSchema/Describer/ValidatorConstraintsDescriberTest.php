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
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ValidatorConstraintsDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ValidatorConstraintsFixture;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ValidatorConstraintsIntlFixture;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Validator\Constraints\Xml;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

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
    #[RequiresMethod(Yaml::class, 'parse')]
    public function testDescribe(string $property, ?array $initialSchema, array $expectedSchema)
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $describer = new ValidatorConstraintsDescriber($validator);
        $propertyReflection = new \ReflectionProperty(ValidatorConstraintsFixture::class, $property);

        $schema = $initialSchema;
        $describer->describeProperty(new PropertySubject($property, $propertyReflection), $schema);

        $this->assertSame($expectedSchema, $schema);
    }

    #[RequiresMethod(Xml::class, '__construct')]
    #[RequiresPhpExtension('simplexml')]
    public function testDescribeXml()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $describer = new ValidatorConstraintsDescriber($validator);
        $propertyReflection = new \ReflectionProperty(ValidatorConstraintsFixture::class, 'xml');

        $schema = null;
        $describer->describeProperty(new PropertySubject('xml', $propertyReflection), $schema);

        $this->assertSame(['contentMediaType' => 'application/xml'], $schema);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<mixed>|null, 2: array<mixed>}>
     */
    public static function provideDescribeCases(): iterable
    {
        yield 'AtLeastOneOf' => ['atLeastOneOf', null, ['anyOf' => [['const' => 'a'], ['type' => 'integer']]]];
        yield 'All' => ['all', null, ['type' => 'array', 'items' => ['maxLength' => 255]]];
        yield 'All, non-array type' => ['all', ['type' => 'string'], ['type' => 'string']];
        yield 'Blank string' => ['blankString', ['type' => 'string'], ['type' => 'string', 'nullable' => true, 'maxLength' => 0]];
        yield 'Collection' => ['collection', null, ['type' => 'object', 'properties' => ['a' => ['const' => 'hello'], 'b' => ['const' => 5]], 'required' => ['a', 'b']]];
        yield 'Collection, non-object type ' => ['collection', ['type' => 'array'], ['type' => 'array']];
        yield 'Count and unique array' => ['countedArray', null, ['minItems' => 2, 'maxItems' => 4, 'uniqueItems' => true]];
        yield 'CssColor' => ['cssColor', ['description' => 'Background color.'], ['description' => "Background color.\nCSS color in one of the following formats: hex_short, hex_long"]];
        yield 'Choice string' => ['choiceString', null, ['enum' => ['a', 'b']]];
        yield 'Choice array' => ['choiceArray', ['type' => 'array', 'items' => ['type' => 'string']], ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['x', 'y']], 'minItems' => 1, 'maxItems' => 2]];
        yield 'Choice callback' => ['choiceCallback', ['type' => 'integer'], ['type' => 'integer', 'enum' => [1, 2, 3]]];
        yield 'Date format' => ['date', null, ['format' => 'date']];
        yield 'DateTime format' => ['dateTime', null, ['format' => 'date-time']];
        yield 'Email format' => ['email', null, ['format' => 'email']];
        yield 'EqualTo' => ['equalTo', null, ['const' => 'foo']];
        yield 'Expression' => ['expression', null, ['description' => 'Must match Symfony Expression Language rule: "this.expression != null"']];
        yield 'ExpressionSyntax' => ['expressionSyntax', null, ['description' => 'Syntax: Symfony Expression Language. Available variables: foo, bar']];
        yield 'Hostname format' => ['hostname', null, ['format' => 'hostname']];
        yield 'IBAN' => ['iban', null, ['description' => 'IBAN without spaces or other separator characters.']];
        yield 'IPv4 format' => ['ipv4', null, ['format' => 'ipv4']];
        yield 'IPv6 format' => ['ipv6', null, ['format' => 'ipv6']];
        yield 'IsFalse const' => ['isFalse', null, ['const' => false]];
        yield 'IsNull const' => ['isNull', ['type' => ['string', 'null']], ['type' => ['string', 'null'], 'const' => null]];
        yield 'IsTrue const' => ['isTrue', null, ['const' => true]];
        yield 'Json' => ['json', null, ['contentMediaType' => 'application/json']];
        yield 'Length string' => ['lengthString', null, ['minLength' => 2, 'maxLength' => 4]];
        yield 'MacAddress' => ['macAddress', null, ['description' => 'MAC address, accepted type: all.']];
        yield 'Negative or zero' => ['negativeNumber', null, ['maximum' => 0]];
        yield 'NotBlank string' => ['notBlankString', ['type' => 'string'], ['type' => 'string', 'nullable' => false, 'minLength' => 1]];
        yield 'NotNull nullable false' => ['notNull', null, ['nullable' => false]];
        yield 'NotNull with null type' => ['notNull', ['type' => ['string', 'null']], ['type' => 'string', 'nullable' => false]];
        yield 'NotEqualTo' => ['notEqualTo', null, ['not' => ['enum' => ['bar']]]];
        yield 'Numeric range' => ['numberRange', null, ['multipleOf' => 3, 'minimum' => 10, 'exclusiveMinimum' => true, 'maximum' => 100]];
        yield 'Positive' => ['positiveNumber', null, ['minimum' => 0, 'exclusiveMinimum' => true]];
        yield 'Range constraint' => ['rangedNumber', null, ['minimum' => 5, 'maximum' => 15]];
        yield 'Regex string' => ['regexString', null, ['pattern' => '[a-z]+']];
        yield 'Time pattern' => ['time', null, ['pattern' => '^([01]\d|2[0-3]):[0-5]\d$']];
        yield 'Timezone' => ['timezone', null, ['description' => 'Timezone in "Region/City" format.']];
        yield 'Type constraint narrows schema type' => ['typedByConstraint', ['type' => ['string', 'null', 'integer']], ['type' => ['string', 'null']]];
        yield 'Ulid pattern' => ['ulid', null, ['pattern' => '^[0-7][0-9A-HJKMNP-TV-Z]{25}$']];
        yield 'Url format' => ['url', null, ['format' => 'uri']];
        yield 'Uuid format' => ['uuid', null, ['format' => 'uuid']];
        yield 'Week' => ['week', null, ['pattern' => '^[0-9]{4}W[0-9]{2}$']];
        yield 'WordCount between' => ['wordCountBetween', null, ['description' => 'Word count must be between 10 and 20.']];
        yield 'WordCount minimum' => ['wordCountMinimum', null, ['description' => 'Word count must be at least 10.']];
        yield 'WordCount maximum' => ['wordCountMaximum', null, ['description' => 'Word count must be no more than 20.']];
        yield 'Yaml' => ['yaml', null, ['contentMediaType' => 'application/yaml']];
    }

    /**
     * @param JsonSchema|array<string, mixed>|null $initialSchema
     * @param JsonSchema|array<string, mixed>      $expectedSchema
     */
    #[DataProvider('describeIntlProvider')]
    #[RequiresMethod(Countries::class, 'exists')]
    public function testDescribeIntl(string $property, ?array $initialSchema, array $expectedSchema)
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $describer = new ValidatorConstraintsDescriber($validator);
        $propertyReflection = new \ReflectionProperty(ValidatorConstraintsIntlFixture::class, $property);

        $schema = $initialSchema;
        $describer->describeProperty(new PropertySubject($property, $propertyReflection), $schema);

        $this->assertSame($expectedSchema, $schema);
    }

    public static function describeIntlProvider(): iterable
    {
        yield 'Country alpha-2' => ['countryAlpha2', null, ['pattern' => '^[A-Z]{2}$', 'description' => 'ISO 3166-1 alpha-2 country code']];
        yield 'Country alpha-3' => ['countryAlpha3', null, ['pattern' => '^[A-Z]{3}$', 'description' => 'ISO 3166-1 alpha-3 country code']];
        yield 'Language alpha-2' => ['languageAlpha2', null, ['pattern' => '^[a-z]{2}$', 'description' => 'ISO 639-1 language code']];
        yield 'Language alpha-3' => ['languageAlpha3', null, ['pattern' => '^[a-z]{3}$', 'description' => 'ISO 639-2 (2T) language code']];
        yield 'Currency' => ['currency', null, ['pattern' => '^[A-Z]{3}$', 'description' => 'ISO 4217 currency code']];
        yield 'Locale' => ['locale', null, ['pattern' => '^[a-z]{2}([_-][A-Z]{2})?$']];
    }
}
