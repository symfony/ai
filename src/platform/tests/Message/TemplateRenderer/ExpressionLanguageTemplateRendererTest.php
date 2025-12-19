<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message\TemplateRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\TemplateRenderer\ExpressionLanguageTemplateRenderer;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class ExpressionLanguageTemplateRendererTest extends TestCase
{
    private ExpressionLanguageTemplateRenderer $renderer;

    protected function setUp(): void
    {
        if (!class_exists(ExpressionLanguage::class)) {
            $this->markTestSkipped('symfony/expression-language is not installed');
        }

        $this->renderer = new ExpressionLanguageTemplateRenderer();
    }

    public function testSupportsExpressionType()
    {
        $this->assertTrue($this->renderer->supports('expression'));
        $this->assertFalse($this->renderer->supports('string'));
        $this->assertFalse($this->renderer->supports('twig'));
    }

    public function testRenderSimpleExpression()
    {
        $template = Template::expression('price * quantity');

        $result = $this->renderer->render($template, [
            'price' => 10,
            'quantity' => 5,
        ]);

        $this->assertSame('50', $result);
    }

    public function testRenderComplexExpression()
    {
        $template = Template::expression('(price * quantity) + tax');

        $result = $this->renderer->render($template, [
            'price' => 10,
            'quantity' => 5,
            'tax' => 5,
        ]);

        $this->assertSame('55', $result);
    }

    public function testRenderStringConcatenation()
    {
        $template = Template::expression('greeting ~ " " ~ name');

        $result = $this->renderer->render($template, [
            'greeting' => 'Hello',
            'name' => 'World',
        ]);

        $this->assertSame('Hello World', $result);
    }

    public function testThrowsExceptionForInvalidExpression()
    {
        $template = Template::expression('invalid expression syntax {');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to render expression template');

        $this->renderer->render($template, []);
    }

    public function testConstructorThrowsExceptionWhenExpressionLanguageNotAvailable()
    {
        if (class_exists(ExpressionLanguage::class)) {
            $this->markTestSkipped('This test requires ExpressionLanguage to not be available');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ExpressionTemplateRenderer requires "symfony/expression-language" package');

        new ExpressionLanguageTemplateRenderer();
    }
}
