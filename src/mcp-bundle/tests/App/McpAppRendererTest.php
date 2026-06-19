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

use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Extension\Apps\UiResourceContentMeta;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Twig\Environment;

final class McpAppRendererTest extends TestCase
{
    public function testRenderProducesMcpAppResourceContents()
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('app.html.twig', ['brand' => 'Acme'])
            ->willReturn('<html>hi</html>');

        $contents = (new McpAppRenderer($twig))->render('ui://acme', 'app.html.twig', ['brand' => 'Acme']);

        $this->assertSame('ui://acme', $contents->uri);
        $this->assertSame(McpApps::MIME_TYPE, $contents->mimeType);
        $this->assertSame('<html>hi</html>', $contents->text);
        $this->assertNull($contents->meta);
    }

    public function testRenderAttachesContentMeta()
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html></html>');

        $meta = new UiResourceContentMeta(prefersBorder: true);
        $contents = (new McpAppRenderer($twig))->render('ui://acme', 'app.html.twig', [], $meta);

        $this->assertSame(['ui' => $meta], $contents->meta);
    }

    public function testRenderFragmentReturnsRenderedHtml()
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('grid.html.twig', ['count' => 2])
            ->willReturn('<div class="grid">…</div>');

        $html = (new McpAppRenderer($twig))->renderFragment('grid.html.twig', ['count' => 2]);

        $this->assertSame('<div class="grid">…</div>', $html);
    }
}
