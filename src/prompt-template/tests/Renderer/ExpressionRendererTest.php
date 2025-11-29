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
use Symfony\AI\PromptTemplate\Exception\RenderingException;
use Symfony\AI\PromptTemplate\Renderer\ExpressionRenderer;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExpressionRendererTest extends TestCase
{
    private ExpressionRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ExpressionRenderer();
    }

    public function testSimpleVariableAccess()
    {
        $result = $this->renderer->render('Hello {name}!', ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function testMathExpressions()
    {
        $result = $this->renderer->render(
            'Total: {price * quantity}',
            ['price' => 10, 'quantity' => 5]
        );

        $this->assertSame('Total: 50', $result);
    }

    public function testConditionalExpression()
    {
        $result = $this->renderer->render(
            'Status: {age >= 18 ? "adult" : "minor"}',
            ['age' => 25]
        );

        $this->assertSame('Status: adult', $result);
    }

    public function testConditionalExpressionMinor()
    {
        $result = $this->renderer->render(
            'Status: {age >= 18 ? "adult" : "minor"}',
            ['age' => 15]
        );

        $this->assertSame('Status: minor', $result);
    }

    public function testArrayAccess()
    {
        $result = $this->renderer->render(
            'First item: {items[0]}',
            ['items' => ['apple', 'banana', 'cherry']]
        );

        $this->assertSame('First item: apple', $result);
    }

    public function testObjectPropertyAccess()
    {
        $user = new class {
            public string $name = 'Alice';
            public int $age = 30;
        };

        $result = $this->renderer->render(
            'User: {user.name}, Age: {user.age}',
            ['user' => $user]
        );

        $this->assertSame('User: Alice, Age: 30', $result);
    }

    public function testMultipleExpressions()
    {
        $result = $this->renderer->render(
            'Subtotal: {price * quantity}, Tax: {price * quantity * 0.2}',
            ['price' => 10, 'quantity' => 5]
        );

        $this->assertSame('Subtotal: 50, Tax: 10', $result);
    }

    public function testEmptyTemplate()
    {
        $result = $this->renderer->render('', ['name' => 'Alice']);

        $this->assertSame('', $result);
    }

    public function testTemplateWithNoPlaceholders()
    {
        $result = $this->renderer->render('Static text', ['name' => 'Alice']);

        $this->assertSame('Static text', $result);
    }

    public function testInvalidExpressionThrowsException()
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('Failed to render expression');

        $this->renderer->render('Result: {invalid syntax}', []);
    }

    public function testMissingVariableThrowsException()
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('Failed to render expression "missing"');

        $this->renderer->render('Value: {missing}', []);
    }

    public function testCustomExpressionLanguageInstance()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->register('double', fn ($str) => \sprintf('(%s * 2)', $str), fn (array $values, $value) => $value * 2);

        $renderer = new ExpressionRenderer($expressionLanguage);
        $result = $renderer->render('Double: {double(number)}', ['number' => 21]);

        $this->assertSame('Double: 42', $result);
    }

    public function testStringConcatenation()
    {
        $result = $this->renderer->render(
            'Full name: {firstName ~ " " ~ lastName}',
            ['firstName' => 'John', 'lastName' => 'Doe']
        );

        $this->assertSame('Full name: John Doe', $result);
    }

    public function testComparisonOperators()
    {
        $result = $this->renderer->render(
            'Greater: {10 > 5}, Equal: {5 == 5}, Less: {3 < 2}',
            []
        );

        $this->assertSame('Greater: 1, Equal: 1, Less: ', $result);
    }
}
