<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\PromptTemplate\PromptTemplate;
use Symfony\AI\PromptTemplate\Renderer\ExpressionRenderer;
use Symfony\AI\PromptTemplate\Renderer\RendererInterface;
use Symfony\AI\PromptTemplate\Renderer\StringRenderer;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PromptTemplateTest extends TestCase
{
    public function testConstructorWithDefaultRenderer()
    {
        $template = new PromptTemplate('Hello {name}!');
        $result = $template->format(['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function testConstructorWithCustomRenderer()
    {
        $renderer = new ExpressionRenderer();
        $template = new PromptTemplate('Total: {price * quantity}', $renderer);
        $result = $template->format(['price' => 10, 'quantity' => 5]);

        $this->assertSame('Total: 50', $result);
    }

    public function testFormatWithEmptyValues()
    {
        $template = new PromptTemplate('Hello {name}!');
        $result = $template->format([]);

        $this->assertSame('Hello {name}!', $result);
    }

    public function testFormatWithMultipleValues()
    {
        $template = new PromptTemplate('User: {user}, Query: {query}');
        $result = $template->format(['user' => 'Alice', 'query' => 'What is AI?']);

        $this->assertSame('User: Alice, Query: What is AI?', $result);
    }

    public function testGetTemplateReturnsOriginalTemplate()
    {
        $originalTemplate = 'Hello {name}!';
        $template = new PromptTemplate($originalTemplate);

        $this->assertSame($originalTemplate, $template->getTemplate());
    }

    public function testToStringReturnsTemplate()
    {
        $originalTemplate = 'Hello {name}!';
        $template = new PromptTemplate($originalTemplate);

        $this->assertSame($originalTemplate, (string) $template);
    }

    public function testFromStringStaticFactory()
    {
        $template = PromptTemplate::fromString('Hello {name}!');
        $result = $template->format(['name' => 'World']);

        $this->assertSame('Hello World!', $result);
        $this->assertInstanceOf(PromptTemplate::class, $template);
    }

    public function testFromStringWithRendererStaticFactory()
    {
        $renderer = new ExpressionRenderer();
        $template = PromptTemplate::fromStringWithRenderer(
            'Result: {a + b}',
            $renderer
        );
        $result = $template->format(['a' => 5, 'b' => 3]);

        $this->assertSame('Result: 8', $result);
        $this->assertInstanceOf(PromptTemplate::class, $template);
    }

    public function testIntegrationWithStringRenderer()
    {
        $renderer = new StringRenderer();
        $template = new PromptTemplate('Greeting: {greeting}', $renderer);
        $result = $template->format(['greeting' => 'Hello World']);

        $this->assertSame('Greeting: Hello World', $result);
    }

    public function testIntegrationWithExpressionRenderer()
    {
        $renderer = new ExpressionRenderer();
        $template = new PromptTemplate('Status: {age >= 18 ? "adult" : "minor"}', $renderer);
        $result = $template->format(['age' => 25]);

        $this->assertSame('Status: adult', $result);
    }

    public function testCustomRendererImplementation()
    {
        $customRenderer = new class implements RendererInterface {
            public function render(string $template, array $values): string
            {
                return strtoupper($template);
            }
        };

        $template = new PromptTemplate('hello world', $customRenderer);
        $result = $template->format([]);

        $this->assertSame('HELLO WORLD', $result);
    }

    public function testTemplateIsImmutable()
    {
        $template = new PromptTemplate('Hello {name}!');
        $result1 = $template->format(['name' => 'Alice']);
        $result2 = $template->format(['name' => 'Bob']);

        $this->assertSame('Hello Alice!', $result1);
        $this->assertSame('Hello Bob!', $result2);
        $this->assertSame('Hello {name}!', $template->getTemplate());
    }

    public function testEmptyTemplate()
    {
        $template = new PromptTemplate('');
        $result = $template->format(['name' => 'World']);

        $this->assertSame('', $result);
        $this->assertSame('', (string) $template);
    }

    public function testComplexMultilineTemplate()
    {
        $templateString = <<<'TEMPLATE'
You are a helpful assistant.

User: {user}
Query: {query}
Context: {context}
TEMPLATE;

        $template = new PromptTemplate($templateString);
        $result = $template->format([
            'user' => 'Alice',
            'query' => 'What is AI?',
            'context' => 'Education',
        ]);

        $expected = <<<'EXPECTED'
You are a helpful assistant.

User: Alice
Query: What is AI?
Context: Education
EXPECTED;

        $this->assertSame($expected, $result);
    }
}
