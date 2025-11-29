<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate\Tests\Renderer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\PromptTemplate\Exception\InvalidArgumentException;
use Symfony\AI\PromptTemplate\Renderer\StringRenderer;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class StringRendererTest extends TestCase
{
    private StringRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new StringRenderer();
    }

    public function testSimpleSingleVariableReplacement()
    {
        $result = $this->renderer->render('Hello {name}!', ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function testMultipleVariables()
    {
        $result = $this->renderer->render(
            'User: {user}, Query: {query}',
            ['user' => 'Alice', 'query' => 'What is AI?']
        );

        $this->assertSame('User: Alice, Query: What is AI?', $result);
    }

    public function testVariableUsedMultipleTimes()
    {
        $result = $this->renderer->render(
            '{name} said: "Hello, {name}!"',
            ['name' => 'Bob']
        );

        $this->assertSame('Bob said: "Hello, Bob!"', $result);
    }

    public function testNumericValues()
    {
        $result = $this->renderer->render(
            'Price: {price}, Quantity: {quantity}',
            ['price' => 10, 'quantity' => 5]
        );

        $this->assertSame('Price: 10, Quantity: 5', $result);
    }

    public function testStringableObject()
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable Object';
            }
        };

        $result = $this->renderer->render('Value: {obj}', ['obj' => $stringable]);

        $this->assertSame('Value: Stringable Object', $result);
    }

    public function testMissingVariablesAreLeftUnchanged()
    {
        $result = $this->renderer->render('Hello {name} and {friend}!', ['name' => 'Alice']);

        $this->assertSame('Hello Alice and {friend}!', $result);
    }

    public function testEmptyTemplate()
    {
        $result = $this->renderer->render('', ['name' => 'Alice']);

        $this->assertSame('', $result);
    }

    public function testEmptyValues()
    {
        $result = $this->renderer->render('Hello {name}!', []);

        $this->assertSame('Hello {name}!', $result);
    }

    public function testTemplateWithNoPlaceholders()
    {
        $result = $this->renderer->render('Static text', ['name' => 'Alice']);

        $this->assertSame('Static text', $result);
    }

    public function testSpecialCharactersInValues()
    {
        $result = $this->renderer->render(
            'Message: {msg}',
            ['msg' => 'Special chars: ${}[]()']
        );

        $this->assertSame('Message: Special chars: ${}[]()', $result);
    }

    public function testUnicodeCharacters()
    {
        $result = $this->renderer->render(
            'Greeting: {greeting}',
            ['greeting' => 'こんにちは 世界']
        );

        $this->assertSame('Greeting: こんにちは 世界', $result);
    }

    public function testInvalidVariableNameThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variable name must be a string, "int" given.');

        // @phpstan-ignore argument.type
        $this->renderer->render('Hello {name}!', [0 => 'Alice']);
    }

    public function testNonStringableValueThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variable "data" must be a string, numeric, or Stringable, "array" given.');

        $this->renderer->render('Data: {data}', ['data' => ['foo' => 'bar']]);
    }

    public function testObjectWithoutStringableThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variable "obj" must be a string, numeric, or Stringable');

        $this->renderer->render('Object: {obj}', ['obj' => new \stdClass()]);
    }
}
