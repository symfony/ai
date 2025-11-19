<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Fixtures\StructuredOutput\User;
use Symfony\AI\Fixtures\StructuredOutput\UserWithConstructor;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Fixtures\Tool\ToolWithoutDocs;
use Symfony\AI\Platform\Contract\JsonSchema\DescriptionParser;

final class DescriptionParserTest extends TestCase
{
    public function testFromPropertyWithoutDocBlock()
    {
        $property = new \ReflectionProperty(User::class, 'id');

        $actual = (new DescriptionParser())->getDescription($property);

        $this->assertSame('', $actual);
    }

    public function testFromPropertyWithDocBlock()
    {
        $property = new \ReflectionProperty(User::class, 'name');

        $actual = (new DescriptionParser())->getDescription($property);

        $this->assertSame('The name of the user in lowercase', $actual);
    }

    public function testFromPropertyWithConstructorDocBlock()
    {
        $property = new \ReflectionProperty(UserWithConstructor::class, 'name');

        $actual = (new DescriptionParser())->getDescription($property);

        $this->assertSame('The name of the user in lowercase', $actual);
    }

    public function testFromParameterWithoutDocBlock()
    {
        $parameter = new \ReflectionParameter([ToolWithoutDocs::class, 'bar'], 'text');

        $actual = (new DescriptionParser())->getDescription($parameter);

        $this->assertSame('', $actual);
    }

    public function testFromParameterWithDocBlock()
    {
        $parameter = new \ReflectionParameter([ToolRequiredParams::class, 'bar'], 'text');

        $actual = (new DescriptionParser())->getDescription($parameter);

        $this->assertSame('The text given to the tool', $actual);
    }

    #[DataProvider('provideMethodDescriptionCases')]
    public function testFromParameterWithDocs(string $comment, string $expected)
    {
        $method = self::createMock(\ReflectionMethod::class);
        $method->method('getDocComment')->willReturn($comment);
        $parameter = self::createMock(\ReflectionParameter::class);
        $parameter->method('getDeclaringFunction')->willReturn($method);
        $parameter->method('getName')->willReturn('myParam');

        $actual = (new DescriptionParser())->getDescription($parameter);

        $this->assertSame($expected, $actual);
    }

    public static function provideMethodDescriptionCases(): \Generator
    {
        yield 'empty doc block' => [
            'comment' => '',
            'expected' => '',
        ];

        yield 'single line doc block with description' => [
            'comment' => '/** @param string $myParam The description */',
            'expected' => 'The description',
        ];

        yield 'multi line doc block with description and other tags' => [
            'comment' => <<<'TEXT'
                    /**
                     * @param string $myParam The description
                     * @return void
                     */
                TEXT,
            'expected' => 'The description',
        ];

        yield 'multi line doc block with multiple parameters' => [
            'comment' => <<<'TEXT'
                    /**
                     * @param string $myParam The description
                     * @param string $anotherParam The wrong description
                     */
                TEXT,
            'expected' => 'The description',
        ];

        yield 'multi line doc block with parameter that is not searched for' => [
            'comment' => <<<'TEXT'
                    /**
                     * @param string $unknownParam The description
                     */
                TEXT,
            'expected' => '',
        ];
    }
}
