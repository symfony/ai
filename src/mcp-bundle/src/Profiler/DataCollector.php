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

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * Collects MCP server capabilities for the Web Profiler.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
final class DataCollector extends AbstractDataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly TraceableRegistry $registry,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $tools = [];
        foreach ($this->registry->getTools()->references as $tool) {
            $tools[] = [
                'name' => $tool->name,
                'description' => $tool->description,
                'inputSchema' => $tool->inputSchema,
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
            ];
        }

        $resources = [];
        foreach ($this->registry->getResources()->references as $resource) {
            $resources[] = [
                'uri' => $resource->uri,
                'name' => $resource->name,
                'description' => $resource->description,
                'mimeType' => $resource->mimeType,
            ];
        }

        $resourceTemplates = [];
        foreach ($this->registry->getResourceTemplates()->references as $template) {
            $resourceTemplates[] = [
                'uriTemplate' => $template->uriTemplate,
                'name' => $template->name,
                'description' => $template->description,
                'mimeType' => $template->mimeType,
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
     * @return array<array{name: string, description: ?string, inputSchema: array<mixed>}>
     */
    public function getTools(): array
    {
        return $this->data['tools'] ?? [];
    }

    /**
     * @return array<array{name: string, description: ?string, arguments: array<mixed>}>
     */
    public function getPrompts(): array
    {
        return $this->data['prompts'] ?? [];
    }

    /**
     * @return array<array{uri: string, name: string, description: ?string, mimeType: ?string}>
     */
    public function getResources(): array
    {
        return $this->data['resources'] ?? [];
    }

    /**
     * @return array<array{uriTemplate: string, name: string, description: ?string, mimeType: ?string}>
     */
    public function getResourceTemplates(): array
    {
        return $this->data['resourceTemplates'] ?? [];
    }

    public function getTotalCount(): int
    {
        return \count($this->getTools()) + \count($this->getPrompts()) + \count($this->getResources()) + \count($this->getResourceTemplates());
    }

    public static function getTemplate(): string
    {
        return '@Mcp/data_collector.html.twig';
    }
}
