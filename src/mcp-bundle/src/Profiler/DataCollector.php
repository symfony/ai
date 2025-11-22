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

use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Symfony\AI\McpBundle\Profiler\Loader\ProfilingLoader;
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
        private readonly ProfilingLoader $profilingLoader,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $registry = $this->profilingLoader->getRegistry();

        if (null === $registry) {
            $this->data = [
                'tools' => [],
                'prompts' => [],
                'resources' => [],
                'resourceTemplates' => [],
            ];

            return;
        }

        $tools = [];
        foreach ($registry->getTools()->references as $item) {
            if (!$item instanceof Tool) {
                continue;
            }

            $tools[] = [
                'name' => $item->name,
                'description' => $item->description,
                'inputSchema' => $item->inputSchema,
            ];
        }

        $prompts = [];
        foreach ($registry->getPrompts()->references as $item) {
            if (!$item instanceof Prompt) {
                continue;
            }

            $prompts[] = [
                'name' => $item->name,
                'description' => $item->description,
                'arguments' => array_map(fn ($arg) => [
                    'name' => $arg->name,
                    'description' => $arg->description,
                    'required' => $arg->required,
                ], $item->arguments ?? []),
            ];
        }

        $resources = [];
        foreach ($registry->getResources()->references as $item) {
            if (!$item instanceof Resource) {
                continue;
            }

            $resources[] = [
                'uri' => $item->uri,
                'name' => $item->name,
                'description' => $item->description,
                'mimeType' => $item->mimeType,
            ];
        }

        $resourceTemplates = [];
        foreach ($registry->getResourceTemplates()->references as $item) {
            if (!$item instanceof ResourceTemplate) {
                continue;
            }

            $resourceTemplates[] = [
                'uriTemplate' => $item->uriTemplate,
                'name' => $item->name,
                'description' => $item->description,
                'mimeType' => $item->mimeType,
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
