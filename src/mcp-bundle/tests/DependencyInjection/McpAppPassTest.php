<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\DependencyInjection;

use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Extension\Apps\ToolVisibility;
use Mcp\Schema\Extension\Apps\UiResourceContentMeta;
use Mcp\Schema\Extension\Apps\UiToolMeta;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\App\McpAppResourceRenderer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Attribute\AsMcpAppTool;
use Symfony\AI\McpBundle\DependencyInjection\McpAppPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class McpAppPassTest extends TestCase
{
    public function testTemplateAppEnablesExtensionAndAddsResource()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(StaticTemplateApp::class, (new Definition(StaticTemplateApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();

        $enable = $this->callsNamed($calls, 'enableExtension');
        $this->assertCount(1, $enable);
        $this->assertInstanceOf(Definition::class, $enable[0][1][0]);
        $this->assertSame(McpApps::class, $enable[0][1][0]->getClass());

        $resources = $this->callsNamed($calls, 'addResource');
        $this->assertCount(1, $resources);
        $args = $resources[0][1];

        // uri defaults to the kebab-cased short class name; resource name is the URI slug
        $this->assertSame('ui://static-template-app', $args[1]);
        $this->assertSame('static-template-app', $args[2]);
        $this->assertSame(McpApps::MIME_TYPE, $args[5]);

        // descriptor marker is a stdClass produced by McpApps::resourceMarker()
        $marker = $args[9]['ui'];
        $this->assertInstanceOf(Definition::class, $marker);
        $this->assertSame(\stdClass::class, $marker->getClass());
        $this->assertSame([McpApps::class, 'resourceMarker'], $marker->getFactory());

        // template apps share one URI-dispatched renderer service (keyed by its FQCN)
        $this->assertSame([McpAppResourceRenderer::class, '__invoke'], $args[0]);
        $this->assertTrue($container->hasDefinition(McpAppResourceRenderer::class));
        $rendererDef = $container->getDefinition(McpAppResourceRenderer::class);
        $this->assertArrayHasKey('mcp.resource', $rendererDef->getTags());

        // the renderer's URI => {template, contentMeta} map carries this app
        $map = $rendererDef->getArgument(1);
        $this->assertArrayHasKey('ui://static-template-app', $map);
        $this->assertSame('dashboard.html.twig', $map['ui://static-template-app']['template']);

        // content-meta built from the attribute (csp connect + geolocation + prefersBorder)
        $contentMeta = $map['ui://static-template-app']['contentMeta'];
        $this->assertInstanceOf(Definition::class, $contentMeta);
        $this->assertSame(UiResourceContentMeta::class, $contentMeta->getClass());
        $this->assertNull($contentMeta->getArgument(2)); // domain
        $this->assertTrue($contentMeta->getArgument(3)); // prefersBorder
    }

    public function testCustomInvokeAppUsesClassHandlerAndNeedsNoRenderer()
    {
        $container = $this->containerWithBuilder(withRenderer: false);
        $container->setDefinition(CustomHandlerApp::class, (new Definition(CustomHandlerApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $resources = $this->callsNamed($calls, 'addResource');
        $this->assertCount(1, $resources);
        $this->assertSame([CustomHandlerApp::class, '__invoke'], $resources[0][1][0]);
        $this->assertSame('ui://custom', $resources[0][1][1]);

        // the user class itself is tagged mcp.resource so McpPass adds it to the locator
        $this->assertArrayHasKey('mcp.resource', $container->getDefinition(CustomHandlerApp::class)->getTags());
    }

    public function testExtensionEnabledOnlyOnceForMultipleApps()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(StaticTemplateApp::class, (new Definition(StaticTemplateApp::class))->addTag('mcp.app'));
        $container->setDefinition(CustomHandlerApp::class, (new Definition(CustomHandlerApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $this->assertCount(1, $this->callsNamed($calls, 'enableExtension'));
        $this->assertCount(2, $this->callsNamed($calls, 'addResource'));
    }

    public function testDisabledFlagRegistersNothing()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setParameter('mcp.apps.enabled', false);
        $container->setDefinition(StaticTemplateApp::class, (new Definition(StaticTemplateApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $this->assertEmpty($container->getDefinition('mcp.server.builder')->getMethodCalls());
    }

    public function testForcedEnabledWithoutAppsEnablesExtensionOnly()
    {
        $container = $this->containerWithBuilder(withRenderer: false);
        $container->setParameter('mcp.apps.enabled', true);

        (new McpAppPass())->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $this->assertCount(1, $this->callsNamed($calls, 'enableExtension'));
        $this->assertCount(0, $this->callsNamed($calls, 'addResource'));
    }

    public function testAutoModeWithoutAppsDoesNothing()
    {
        $container = $this->containerWithBuilder(withRenderer: false);

        (new McpAppPass())->process($container);

        $this->assertEmpty($container->getDefinition('mcp.server.builder')->getMethodCalls());
    }

    public function testTemplateAppWithoutTwigThrows()
    {
        $container = $this->containerWithBuilder(withRenderer: false);
        $container->setDefinition(StaticTemplateApp::class, (new Definition(StaticTemplateApp::class))->addTag('mcp.app'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Twig is not available/');

        (new McpAppPass())->process($container);
    }

    public function testRenderMethodRegistersLinkedTool()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(WidgetApp::class, (new Definition(WidgetApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $tools = $this->callsNamed($calls, 'addTool');
        $this->assertCount(1, $tools);

        $args = $tools[0][1];
        $this->assertSame([WidgetApp::class, 'render'], $args[0]);
        $this->assertSame('do_widget', $args[1]);     // tool name (from #[AsMcpApp] name)
        $this->assertSame('Widget', $args[2]);         // title
        $this->assertSame('Does a widget', $args[3]);  // tool description

        // ui link auto-set to this app
        $ui = $args[7]['ui'];
        $this->assertInstanceOf(Definition::class, $ui);
        $this->assertSame(UiToolMeta::class, $ui->getClass());
        $this->assertSame('ui://widget', $ui->getArgument(0));
        $this->assertSame([ToolVisibility::Model, ToolVisibility::App], $ui->getArgument(1));

        // app service tagged mcp.tool so McpPass adds it to the handler locator
        $this->assertArrayHasKey('mcp.tool', $container->getDefinition(WidgetApp::class)->getTags());
    }

    public function testTemplateAppWithoutRenderMethodRegistersNoTool()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(StaticTemplateApp::class, (new Definition(StaticTemplateApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $this->assertCount(0, $this->callsNamed($calls, 'addTool'));
    }

    public function testExplicitMissingToolMethodThrows()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(BadMethodApp::class, (new Definition(BadMethodApp::class))->addTag('mcp.app'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/has no such method/');

        (new McpAppPass())->process($container);
    }

    public function testToolTemplateAndAppToolTemplatesAreExported()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(MultiToolApp::class, (new Definition(MultiToolApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $templates = $container->getParameter('mcp.apps.tool_templates');
        $this->assertSame('grid.html.twig', $templates[MultiToolApp::class.'::render']);
        $this->assertSame('detail.html.twig', $templates[MultiToolApp::class.'::showDetail']);
        $this->assertArrayNotHasKey(MultiToolApp::class.'::plain', $templates); // no template declared
    }

    public function testAppToolMethodsRegisterAdditionalLinkedTools()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(MultiToolApp::class, (new Definition(MultiToolApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $byName = [];
        foreach ($this->callsNamed($calls, 'addTool') as $tool) {
            $byName[$tool[1][1]] = $tool[1];
        }

        // primary tool + two #[AsMcpAppTool] methods
        $this->assertSame(['do_multi', 'detail', 'plain'], array_keys($byName));

        // follow-up tool inherits the app URI and is app-only
        $detailUi = $byName['detail'][7]['ui'];
        $this->assertSame(UiToolMeta::class, $detailUi->getClass());
        $this->assertSame('ui://multi', $detailUi->getArgument(0));
        $this->assertSame([ToolVisibility::App], $detailUi->getArgument(1));
        $this->assertSame([MultiToolApp::class, 'showDetail'], $byName['detail'][0]);

        // default tool name is the snake_cased method; default visibility is model + app
        $this->assertSame([ToolVisibility::Model, ToolVisibility::App], $byName['plain'][7]['ui']->getArgument(1));
    }

    public function testPrimaryMethodWithAppToolAttributeThrows()
    {
        $container = $this->containerWithBuilder(withRenderer: true);
        $container->setDefinition(CollidingToolApp::class, (new Definition(CollidingToolApp::class))->addTag('mcp.app'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/primary tool/');

        (new McpAppPass())->process($container);
    }

    public function testToolTemplateWithoutTwigThrows()
    {
        $container = $this->containerWithBuilder(withRenderer: false);
        $container->setDefinition(InvokeWithToolTemplateApp::class, (new Definition(InvokeWithToolTemplateApp::class))->addTag('mcp.app'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Twig is not available/');

        (new McpAppPass())->process($container);
    }

    public function testDoesNothingWhenNoServerBuilder()
    {
        $container = new ContainerBuilder();
        $container->setDefinition(StaticTemplateApp::class, (new Definition(StaticTemplateApp::class))->addTag('mcp.app'));

        (new McpAppPass())->process($container);

        $this->assertFalse($container->hasDefinition('mcp.server.builder'));
    }

    private function containerWithBuilder(bool $withRenderer): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('mcp.server.builder', new Definition());
        if ($withRenderer) {
            $container->setDefinition(McpAppRenderer::SERVICE_ID, new Definition(McpAppRenderer::class));
        }

        return $container;
    }

    /**
     * @param array<array{0: string, 1: array<mixed>}> $calls
     *
     * @return array<array{0: string, 1: array<mixed>}>
     */
    private function callsNamed(array $calls, string $name): array
    {
        return array_values(array_filter($calls, static fn ($call) => $name === $call[0]));
    }
}

#[AsMcpApp(template: 'dashboard.html.twig', prefersBorder: true, cspConnect: ['https://api.example.com'], geolocation: true)]
class StaticTemplateApp
{
}

#[AsMcpApp(uri: 'ui://custom')]
class CustomHandlerApp
{
    public function __invoke(): TextResourceContents
    {
        return new TextResourceContents('ui://custom', McpApps::MIME_TYPE, '<html></html>');
    }
}

#[AsMcpApp(uri: 'ui://widget', name: 'do_widget', title: 'Widget', description: 'Does a widget', template: 'widget.html.twig')]
class WidgetApp
{
    /**
     * @return array<string, mixed>
     */
    public function render(int $id): array
    {
        return ['id' => $id];
    }
}

#[AsMcpApp(template: 'bad.html.twig', method: 'missing')]
class BadMethodApp
{
}

#[AsMcpApp(uri: 'ui://multi', name: 'do_multi', title: 'Multi', description: 'Primary', template: 'shell.html.twig', toolTemplate: 'grid.html.twig')]
class MultiToolApp
{
    /**
     * @return array<string, mixed>
     */
    public function render(string $query = ''): array
    {
        return ['query' => $query];
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMcpAppTool(name: 'detail', description: 'Show details', template: 'detail.html.twig', appOnly: true)]
    public function showDetail(string $slug): array
    {
        return ['slug' => $slug];
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMcpAppTool]
    public function plain(): array
    {
        return [];
    }
}

#[AsMcpApp(uri: 'ui://collide', name: 'collide', template: 'shell.html.twig')]
class CollidingToolApp
{
    /**
     * @return array<string, mixed>
     */
    #[AsMcpAppTool]
    public function render(): array
    {
        return [];
    }
}

#[AsMcpApp(uri: 'ui://inv', toolTemplate: 'grid.html.twig')]
class InvokeWithToolTemplateApp
{
    public function __invoke(): TextResourceContents
    {
        return new TextResourceContents('ui://inv', McpApps::MIME_TYPE, '<html></html>');
    }

    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        return [];
    }
}
