<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\App;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\App\McpAppReferenceHandler;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\Exception\LogicException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpAppReferenceHandlerTest extends TestCase
{
    public function testRendersTemplateForRegisteredToolIntoHtml()
    {
        $handler = $this->handler(['name' => 'World'], ['App\\Foo::render' => 'frag.html.twig']);

        $result = $handler->handle(new ElementReference(['App\\Foo', 'render']), []);

        $this->assertSame('Hello World', $result['html']);
        $this->assertSame('World', $result['name']); // original context preserved
    }

    public function testBundleRenderedHtmlOverridesAnyHtmlKeyInContext()
    {
        $handler = $this->handler(['html' => 'manual', 'name' => 'World'], ['App\\Foo::render' => 'frag.html.twig']);

        $result = $handler->handle(new ElementReference(['App\\Foo', 'render']), []);

        // the bundle's rendered fragment is authoritative for a template-bound tool
        $this->assertSame('Hello World', $result['html']);
    }

    public function testUnregisteredHandlerPassesThrough()
    {
        $handler = $this->handler(['name' => 'World'], ['App\\Other::render' => 'frag.html.twig']);

        $result = $handler->handle(new ElementReference(['App\\Foo', 'render']), []);

        $this->assertSame(['name' => 'World'], $result);
    }

    public function testNonArrayHandlerPassesThrough()
    {
        $handler = $this->handler('plain text', ['App\\Foo::render' => 'frag.html.twig']);

        $result = $handler->handle(new ElementReference('some_function'), []);

        $this->assertSame('plain text', $result);
    }

    public function testRegisteredToolReturningNonArrayThrows()
    {
        $handler = $this->handler('not an array', ['App\\Foo::render' => 'frag.html.twig']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/must return an array context/');

        $handler->handle(new ElementReference(['App\\Foo', 'render']), []);
    }

    public function testRegisteredToolReturningListThrows()
    {
        $handler = $this->handler(['a', 'b'], ['App\\Foo::render' => 'frag.html.twig']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/must return an array context/');

        $handler->handle(new ElementReference(['App\\Foo', 'render']), []);
    }

    /**
     * @param array<string, string> $templates
     */
    private function handler(mixed $innerResult, array $templates): McpAppReferenceHandler
    {
        $inner = new class($innerResult) implements ReferenceHandlerInterface {
            public function __construct(private readonly mixed $result)
            {
            }

            public function handle(ElementReference $reference, array $arguments): mixed
            {
                return $this->result;
            }
        };

        $renderer = new McpAppRenderer(new Environment(new ArrayLoader(['frag.html.twig' => 'Hello {{ name }}'])));

        return new McpAppReferenceHandler($inner, $renderer, $templates);
    }
}
