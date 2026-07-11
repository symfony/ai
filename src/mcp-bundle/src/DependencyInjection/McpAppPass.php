<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\DependencyInjection;

use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Extension\Apps\ToolVisibility;
use Mcp\Schema\Extension\Apps\UiResourceContentMeta;
use Mcp\Schema\Extension\Apps\UiResourceCsp;
use Mcp\Schema\Extension\Apps\UiResourcePermissions;
use Mcp\Schema\Extension\Apps\UiToolMeta;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\AI\McpBundle\App\McpAppResourceRenderer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Attribute\AsMcpAppTool;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires classes carrying {@see AsMcpApp} into the MCP server builder.
 *
 * For each app it enables the MCP Apps server extension (once), registers the UI resource carrying the
 * `_meta.ui` resource-descriptor marker (a `stdClass`, which a plain attribute cannot express), and —
 * when the app declares a handler method — registers the linked tool with its `ui` link auto-set to
 * this app.
 *
 * Template-based apps share a single {@see McpAppResourceRenderer} service (the SDK resolves a manual
 * handler's instance by its class name, so a per-app service is not possible); the renderer dispatches
 * on the requested URI. Must run BEFORE {@see McpPass}: the services it tags `mcp.tool`/`mcp.resource`
 * here are what McpPass collects into the handler service locator.
 */
final class McpAppPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $enabledFlag = $container->hasParameter('mcp.apps.enabled')
            ? $container->getParameter('mcp.apps.enabled')
            : null;

        // Hard-disabled: register nothing, even if apps are present.
        if (false === $enabledFlag) {
            return;
        }

        $appServiceIds = array_keys($container->findTaggedServiceIds('mcp.app'));
        $builder = $container->getDefinition('mcp.server.builder');

        if ((true === $enabledFlag || [] !== $appServiceIds) && !$this->extensionAlreadyEnabled($builder)) {
            $builder->addMethodCall('enableExtension', [new Definition(McpApps::class)]);
        }

        $templateApps = [];
        $toolTemplates = [];

        foreach ($appServiceIds as $serviceId) {
            $class = $this->resolveClass($container, $serviceId);
            $app = $this->readAttribute($class, $serviceId);

            $uri = $app->uri ?? 'ui://'.$this->kebab($class);
            if (!str_starts_with($uri, McpApps::URI_SCHEME.'://')) {
                throw new LogicException(\sprintf('The MCP App "%s" must use a "%s://" URI, got "%s".', $class, McpApps::URI_SCHEME, $uri));
            }
            $slug = substr($uri, \strlen(McpApps::URI_SCHEME.'://'));

            $handler = $this->resolveResourceHandler($container, $serviceId, $class, $app, $uri, $templateApps);

            $descriptorMarker = (new Definition(\stdClass::class))->setFactory([McpApps::class, 'resourceMarker']);

            $builder->addMethodCall('addResource', [
                $handler,
                $uri,
                $slug, // resource name, derived from the URI ($name is the tool's)
                $app->title,
                null, // resource description (the model-facing description is the tool's)
                McpApps::MIME_TYPE,
                null, // size
                null, // annotations
                null, // icons
                ['ui' => $descriptorMarker],
            ]);

            $this->registerTool($container, $serviceId, $class, $app, $uri, $slug, $builder, $toolTemplates);
            $this->registerAppToolMethods($container, $serviceId, $class, $app, $uri, $builder, $toolTemplates);
        }

        if ([] !== $templateApps) {
            $container->register(McpAppResourceRenderer::class, McpAppResourceRenderer::class)
                ->setArguments([new Reference(McpAppRenderer::SERVICE_ID), $templateApps])
                ->addTag('mcp.resource');
        }

        if ([] !== $toolTemplates && !$container->hasDefinition(McpAppRenderer::SERVICE_ID)) {
            throw new LogicException('An MCP App tool declares a Twig template but Twig is not available. Run "composer require symfony/twig-bundle".');
        }

        // Consumed by McpPass to wire the McpAppReferenceHandler that renders these templates into the
        // tool result's `html` field (keeps Twig out of the developer's tool methods).
        $container->setParameter('mcp.apps.tool_templates', $toolTemplates);
    }

    /**
     * Resolves the resource (HTML body) handler for an app and, for template-based apps, records the
     * template + content metadata into $templateApps for the shared {@see McpAppResourceRenderer}.
     *
     * @param array<string, array{template: string, contentMeta: ?Definition}> $templateApps
     *
     * @return array{0: string, 1: string} the resource handler callable [class, method]
     */
    private function resolveResourceHandler(ContainerBuilder $container, string $serviceId, string $class, AsMcpApp $app, string $uri, array &$templateApps): array
    {
        // A class-level __invoke owns the full response, including its own content metadata.
        if (method_exists($class, '__invoke')) {
            $container->getDefinition($serviceId)->addTag('mcp.resource');

            return [$class, '__invoke'];
        }

        if (null === $app->template) {
            throw new LogicException(\sprintf('The MCP App "%s" must either declare an __invoke() method or set a "template" on #[AsMcpApp].', $class));
        }

        if (!$container->hasDefinition(McpAppRenderer::SERVICE_ID)) {
            throw new LogicException(\sprintf('The MCP App "%s" renders the Twig template "%s" but Twig is not available. Run "composer require symfony/twig-bundle".', $class, $app->template));
        }

        $templateApps[$uri] = ['template' => $app->template, 'contentMeta' => $this->buildContentMeta($app)];

        return [McpAppResourceRenderer::class, '__invoke'];
    }

    /**
     * Registers the app's linked tool from its handler method ($method, default "render"), auto-setting
     * the `ui` link to this app. No-op when the method is absent (a static, tool-less app).
     *
     * @param array<string, string> $toolTemplates map of "<serviceId>::<method>" => fragment template, appended to here
     */
    private function registerTool(ContainerBuilder $container, string $serviceId, string $class, AsMcpApp $app, string $uri, string $slug, Definition $builder, array &$toolTemplates): void
    {
        $method = $app->method ?? 'render';

        if (!method_exists($class, $method)) {
            if (null !== $app->method) {
                throw new LogicException(\sprintf('The MCP App "%s" declares tool method "%s" on #[AsMcpApp] but the class has no such method.', $class, $method));
            }

            return; // no default "render" method → static, tool-less app
        }

        // The primary tool is always visible to both the model and the app.
        $this->addTool($container, $builder, $serviceId, $method, $app->name ?? str_replace('-', '_', $slug), $app->title, $app->description, $uri, false, $app->toolTemplate, $toolTemplates);
    }

    /**
     * Registers each method carrying {@see AsMcpAppTool} as an additional tool linked to this app, with
     * the `ui` link pointing at the app's resource and visibility derived from `appOnly`.
     *
     * @param array<string, string> $toolTemplates map of "<serviceId>::<method>" => fragment template, appended to here
     */
    private function registerAppToolMethods(ContainerBuilder $container, string $serviceId, string $class, AsMcpApp $app, string $uri, Definition $builder, array &$toolTemplates): void
    {
        $primaryMethod = $app->method ?? 'render';

        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $attributes = $reflectionMethod->getAttributes(AsMcpAppTool::class);
            if ([] === $attributes) {
                continue;
            }

            $method = $reflectionMethod->getName();
            if ($method === $primaryMethod) {
                throw new LogicException(\sprintf('The MCP App "%s" method "%s" is the primary tool declared on #[AsMcpApp] and must not also carry #[AsMcpAppTool].', $class, $method));
            }

            $tool = $attributes[0]->newInstance();

            $this->addTool($container, $builder, $serviceId, $method, $tool->name ?? $this->snake($method), $tool->title, $tool->description, $uri, $tool->appOnly, $tool->template, $toolTemplates);
        }
    }

    /**
     * Registers one tool on the server builder: tags the app service so {@see McpPass} adds it to the
     * handler locator, links the tool's `ui` to the app resource (with model/app visibility), and records
     * its fragment template (if any) for {@see \Symfony\AI\McpBundle\App\McpAppReferenceHandler}.
     *
     * @param array<string, string> $toolTemplates map of "<serviceId>::<method>" => fragment template, appended to here
     */
    private function addTool(ContainerBuilder $container, Definition $builder, string $serviceId, string $method, string $name, ?string $title, ?string $description, string $uri, bool $appOnly, ?string $template, array &$toolTemplates): void
    {
        $container->getDefinition($serviceId)->addTag('mcp.tool');

        $visibility = $appOnly ? [ToolVisibility::App] : [ToolVisibility::Model, ToolVisibility::App];

        $builder->addMethodCall('addTool', [
            [$serviceId, $method],
            $name,
            $title,
            $description,
            null, // annotations
            null, // inputSchema (derived from the method signature)
            null, // icons
            ['ui' => new Definition(UiToolMeta::class, [$uri, $visibility])],
            null, // outputSchema
        ]);

        if (null !== $template) {
            $toolTemplates[$serviceId.'::'.$method] = $template;
        }
    }

    private function buildContentMeta(AsMcpApp $app): ?Definition
    {
        $cspDef = null;
        if (null !== $app->cspConnect || null !== $app->cspResource || null !== $app->cspFrame || null !== $app->cspBaseUri) {
            $cspDef = new Definition(UiResourceCsp::class, [$app->cspConnect, $app->cspResource, $app->cspFrame, $app->cspBaseUri]);
        }

        $permsDef = null;
        if ($app->camera || $app->microphone || $app->geolocation || $app->clipboardWrite) {
            $permsDef = new Definition(UiResourcePermissions::class, [$app->camera, $app->microphone, $app->geolocation, $app->clipboardWrite]);
        }

        if (null === $cspDef && null === $permsDef && null === $app->domain && null === $app->prefersBorder) {
            return null;
        }

        return new Definition(UiResourceContentMeta::class, [$cspDef, $permsDef, $app->domain, $app->prefersBorder]);
    }

    private function extensionAlreadyEnabled(Definition $builder): bool
    {
        foreach ($builder->getMethodCalls() as [$method, $arguments]) {
            if ('enableExtension' !== $method) {
                continue;
            }

            foreach ($arguments as $argument) {
                if ($argument instanceof Definition && McpApps::class === $argument->getClass()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveClass(ContainerBuilder $container, string $serviceId): string
    {
        $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;

        return $container->getParameterBag()->resolveValue($class);
    }

    private function readAttribute(string $class, string $serviceId): AsMcpApp
    {
        if (!class_exists($class)) {
            throw new LogicException(\sprintf('The MCP App service "%s" maps to class "%s" which does not exist.', $serviceId, $class));
        }

        $attributes = (new \ReflectionClass($class))->getAttributes(AsMcpApp::class);
        if ([] === $attributes) {
            throw new LogicException(\sprintf('The MCP App service "%s" (class "%s") is tagged "mcp.app" but carries no #[AsMcpApp] attribute.', $serviceId, $class));
        }

        return $attributes[0]->newInstance();
    }

    private function kebab(string $class): string
    {
        $short = false !== ($pos = strrpos($class, '\\')) ? substr($class, $pos + 1) : $class;

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }

    private function snake(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
