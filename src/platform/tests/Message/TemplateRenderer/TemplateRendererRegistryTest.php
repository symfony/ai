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
use Symfony\AI\Platform\Message\TemplateRenderer\StringTemplateRenderer;
use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererInterface;
use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererRegistry;
use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererRegistryInterface;

final class TemplateRendererRegistryTest extends TestCase
{
    public function testGetRendererWithSupportedType()
    {
        $registry = new TemplateRendererRegistry([
            new StringTemplateRenderer(),
        ]);

        $template = Template::string('Hello {name}!');

        $renderer = $registry->getRenderer($template->getType());
        $result = $renderer->render($template, ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function testGetRendererSelectsCorrectRenderer()
    {
        $renderer1 = new class implements TemplateRendererInterface {
            public function supports(string $type): bool
            {
                return false;
            }

            public function render(Template $template, array $variables): string
            {
                return 'should not be called';
            }
        };

        $renderer2 = new StringTemplateRenderer();

        $registry = new TemplateRendererRegistry([$renderer1, $renderer2]);

        $template = Template::string('Hello {name}!');

        $renderer = $registry->getRenderer($template->getType());
        $result = $renderer->render($template, ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
        $this->assertSame($renderer2, $renderer);
    }

    public function testThrowsExceptionForUnsupportedType()
    {
        $registry = new TemplateRendererRegistry([
            new StringTemplateRenderer(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No renderer found for template type "unsupported"');

        $registry->getRenderer('unsupported');
    }

    public function testAcceptsIterableOfRenderers()
    {
        $registry = new TemplateRendererRegistry(new \ArrayIterator([
            new StringTemplateRenderer(),
        ]));

        $template = Template::string('Hello {name}!');

        $renderer = $registry->getRenderer($template->getType());
        $result = $renderer->render($template, ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function testImplementsRegistryInterface()
    {
        $registry = new TemplateRendererRegistry([
            new StringTemplateRenderer(),
        ]);

        $this->assertInstanceOf(TemplateRendererRegistryInterface::class, $registry);
    }

    public function testGetRendererReturnsCorrectRendererForType()
    {
        $stringRenderer = new StringTemplateRenderer();

        $registry = new TemplateRendererRegistry([
            $stringRenderer,
        ]);

        $renderer = $registry->getRenderer('string');

        $this->assertSame($stringRenderer, $renderer);
    }

    public function testGetRendererWithMultipleRenderers()
    {
        $renderer1 = new class implements TemplateRendererInterface {
            public function supports(string $type): bool
            {
                return 'custom' === $type;
            }

            public function render(Template $template, array $variables): string
            {
                return 'custom render';
            }
        };

        $renderer2 = new StringTemplateRenderer();

        $registry = new TemplateRendererRegistry([$renderer1, $renderer2]);

        $customRenderer = $registry->getRenderer('custom');
        $stringRenderer = $registry->getRenderer('string');

        $this->assertSame($renderer1, $customRenderer);
        $this->assertSame($renderer2, $stringRenderer);
    }
}
