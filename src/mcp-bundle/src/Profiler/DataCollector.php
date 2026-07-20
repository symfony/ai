<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Profiler;

use Mcp\Capability\RegistryInterface;
use Mcp\Server\Builder;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * Collects MCP server capabilities for the Web Profiler.
 *
 * Builds the MCP server itself so the registry is populated for every profiled request,
 * not only for requests that actually served the MCP endpoint.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
final class DataCollector extends AbstractDataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly Builder $builder,
        private readonly RegistryInterface $registry,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        // The registry is populated by the loaders when the server is built. Re-building on an MCP
        // request is harmless: the same elements are registered again onto the shared registry.
        $this->builder->build();

        $tools = [];
        foreach ($this->registry->getTools()->references as $tool) {
            $tools[] = [
                'name' => $tool->name,
                'description' => $tool->description,
                'inputSchema' => $tool->inputSchema,
                'handler' => $this->formatHandler($this->registry->getTool($tool->name)->handler),
            ];
        }

        $prompts = [];
        foreach ($this->registry->getPrompts()->references as $prompt) {
            $prompts[] = [
                'name' => $prompt->name,
                'description' => $prompt->description,
                'arguments' => array_map(static fn ($arg) => [
                    'name' => $arg->name,
                    'description' => $arg->description,
                    'required' => $arg->required,
                ], $prompt->arguments ?? []),
                'handler' => $this->formatHandler($this->registry->getPrompt($prompt->name)->handler),
            ];
        }

        $resources = [];
        foreach ($this->registry->getResources()->references as $resource) {
            $resources[] = [
                'uri' => $resource->uri,
                'name' => $resource->name,
                'description' => $resource->description,
                'mimeType' => $resource->mimeType,
                'handler' => $this->formatHandler($this->registry->getResource($resource->uri, false)->handler),
            ];
        }

        $resourceTemplates = [];
        foreach ($this->registry->getResourceTemplates()->references as $template) {
            $resourceTemplates[] = [
                'uriTemplate' => $template->uriTemplate,
                'name' => $template->name,
                'description' => $template->description,
                'mimeType' => $template->mimeType,
                'handler' => $this->formatHandler($this->registry->getResourceTemplate($template->uriTemplate)->handler),
            ];
        }

        $this->data = [
            'tools' => $tools,
            'prompts' => $prompts,
            'resources' => $resources,
            'resourceTemplates' => $resourceTemplates,
        ];
    }

    /**
     * @return array<array{name: string, description: ?string, inputSchema: array<mixed>, handler: string}>
     */
    public function getTools(): array
    {
        return $this->data['tools'] ?? [];
    }

    /**
     * @return array<array{name: string, description: ?string, arguments: array<mixed>, handler: string}>
     */
    public function getPrompts(): array
    {
        return $this->data['prompts'] ?? [];
    }

    /**
     * @return array<array{uri: string, name: string, description: ?string, mimeType: ?string, handler: string}>
     */
    public function getResources(): array
    {
        return $this->data['resources'] ?? [];
    }

    /**
     * @return array<array{uriTemplate: string, name: string, description: ?string, mimeType: ?string, handler: string}>
     */
    public function getResourceTemplates(): array
    {
        return $this->data['resourceTemplates'] ?? [];
    }

    public function getTotalCount(): int
    {
        return \count($this->getTools()) + \count($this->getPrompts()) + \count($this->getResources()) + \count($this->getResourceTemplates());
    }

    public function getName(): string
    {
        return 'mcp';
    }

    public static function getTemplate(): string
    {
        return '@Mcp/data_collector.html.twig';
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function formatHandler(\Closure|array|string $handler): string
    {
        if ($handler instanceof \Closure) {
            return 'Closure';
        }

        if (\is_array($handler)) {
            return \sprintf('%s::%s()', \is_object($handler[0]) ? $handler[0]::class : $handler[0], $handler[1]);
        }

        return class_exists($handler) ? $handler.'::__invoke()' : $handler.'()';
    }
}
