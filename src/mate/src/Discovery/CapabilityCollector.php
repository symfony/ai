<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery;

use Mcp\Capability\Discovery\Discoverer;
use Psr\Log\LoggerInterface;

/**
 * Collects MCP capabilities from discovered extensions.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class CapabilityCollector
{
    private ComposerTypeDiscovery $discovery;

    public function __construct(
        private LoggerInterface $logger,
        private string $rootDir,
    ) {
        $this->discovery = new ComposerTypeDiscovery($rootDir, $logger);
    }

    /**
     * Get extensions to load (mirrors ServeCommand logic).
     *
     * @param array<int, string> $enabledExtensions
     *
     * @return array<string, array{dirs: string[], includes: string[]}>
     */
    public function getExtensionsToLoad(array $enabledExtensions): array
    {
        $extensions = [];

        foreach ($this->discovery->discover($enabledExtensions) as $packageName => $data) {
            $extensions[$packageName] = $data;
        }

        $extensions['_custom'] = $this->discovery->discoverRootProject();

        return $extensions;
    }

    /**
     * Collect all capabilities grouped by extension.
     *
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     *
     * @return array<string, array{tools: array<string, array{name: string, description: string|null, handler: string, input_schema: array<string, mixed>|null}>, resources: array<string, array{uri: string, name: string|null, description: string|null, handler: string, mime_type: string|null}>, prompts: array<string, array{name: string, description: string|null, handler: string, arguments: array<mixed>|null}>, resource_templates: array<string, array{uri_template: string, name: string|null, description: string|null, handler: string}>}>
     */
    public function collectCapabilities(array $extensions): array
    {
        $discoverer = new Discoverer($this->logger);
        $capabilitiesByExtension = [];

        foreach ($extensions as $packageName => $data) {
            $state = $discoverer->discover($this->rootDir, $data['dirs']);

            $capabilitiesByExtension[$packageName] = [
                'tools' => $this->formatTools($state->getTools()),
                'resources' => $this->formatResources($state->getResources()),
                'prompts' => $this->formatPrompts($state->getPrompts()),
                'resource_templates' => $this->formatResourceTemplates($state->getResourceTemplates()),
            ];
        }

        return $capabilitiesByExtension;
    }

    /**
     * Extract handler info (class::method or class name).
     */
    private function getHandlerInfo(mixed $handler): string
    {
        if (\is_array($handler)) {
            [$classOrInstance, $method] = $handler;
            $className = \is_object($classOrInstance)
                ? $classOrInstance::class
                : $classOrInstance;

            return "{$className}::{$method}";
        }

        if (\is_string($handler) && class_exists($handler)) {
            return $handler;
        }

        return 'Closure';
    }

    /**
     * @param array<string, \Mcp\Capability\Registry\ToolReference> $tools
     *
     * @return array<string, array{name: string, description: string|null, handler: string, input_schema: array<string, mixed>|null}>
     */
    private function formatTools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $name => $toolRef) {
            $formatted[$name] = [
                'name' => $name,
                'description' => $toolRef->tool->description,
                'handler' => $this->getHandlerInfo($toolRef->handler),
                'input_schema' => $toolRef->tool->inputSchema,
            ];
        }

        return $formatted;
    }

    /**
     * @param array<string, \Mcp\Capability\Registry\ResourceReference> $resources
     *
     * @return array<string, array{uri: string, name: string|null, description: string|null, handler: string, mime_type: string|null}>
     */
    private function formatResources(array $resources): array
    {
        $formatted = [];
        foreach ($resources as $uri => $resourceRef) {
            $formatted[$uri] = [
                'uri' => $uri,
                'name' => $resourceRef->schema->name,
                'description' => $resourceRef->schema->description,
                'handler' => $this->getHandlerInfo($resourceRef->handler),
                'mime_type' => $resourceRef->schema->mimeType,
            ];
        }

        return $formatted;
    }

    /**
     * @param array<string, \Mcp\Capability\Registry\PromptReference> $prompts
     *
     * @return array<string, array{name: string, description: string|null, handler: string, arguments: array<mixed>|null}>
     */
    private function formatPrompts(array $prompts): array
    {
        $formatted = [];
        foreach ($prompts as $name => $promptRef) {
            $formatted[$name] = [
                'name' => $name,
                'description' => $promptRef->prompt->description,
                'handler' => $this->getHandlerInfo($promptRef->handler),
                'arguments' => $promptRef->prompt->arguments,
            ];
        }

        return $formatted;
    }

    /**
     * @param array<string, \Mcp\Capability\Registry\ResourceTemplateReference> $templates
     *
     * @return array<string, array{uri_template: string, name: string|null, description: string|null, handler: string}>
     */
    private function formatResourceTemplates(array $templates): array
    {
        $formatted = [];
        foreach ($templates as $uriTemplate => $templateRef) {
            $formatted[$uriTemplate] = [
                'uri_template' => $uriTemplate,
                'name' => $templateRef->resourceTemplate->name,
                'description' => $templateRef->resourceTemplate->description,
                'handler' => $this->getHandlerInfo($templateRef->handler),
            ];
        }

        return $formatted;
    }
}
