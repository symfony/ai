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
use Symfony\Contracts\Service\ServiceProviderInterface;

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
    /**
     * @param ServiceProviderInterface<Builder>           $builders
     * @param ServiceProviderInterface<RegistryInterface> $registries
     */
    public function __construct(
        private readonly ServiceProviderInterface $builders,
        private readonly ServiceProviderInterface $registries,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data = ['servers' => []];

        foreach (array_keys($this->registries->getProvidedServices()) as $server) {
            // The registry is populated by the loaders when the server is built. Re-building on an MCP
            // request is harmless: the same elements are registered again onto the shared registry.
            $this->builders->get($server)->build();
            $registry = $this->registries->get($server);

            $tools = [];
            foreach ($registry->getTools()->references as $tool) {
                $tools[] = [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'inputSchema' => $tool->inputSchema,
                    'handler' => $this->formatHandler($registry->getTool($tool->name)->handler),
                ];
            }

            $prompts = [];
            foreach ($registry->getPrompts()->references as $prompt) {
                $prompts[] = [
                    'name' => $prompt->name,
                    'description' => $prompt->description,
                    'arguments' => array_map(static fn ($arg) => [
                        'name' => $arg->name,
                        'description' => $arg->description,
                        'required' => $arg->required,
                    ], $prompt->arguments ?? []),
                    'handler' => $this->formatHandler($registry->getPrompt($prompt->name)->handler),
                ];
            }

            $resources = [];
            foreach ($registry->getResources()->references as $resource) {
                $resources[] = [
                    'uri' => $resource->uri,
                    'name' => $resource->name,
                    'description' => $resource->description,
                    'mimeType' => $resource->mimeType,
                    'handler' => $this->formatHandler($registry->getResource($resource->uri, false)->handler),
                ];
            }

            $resourceTemplates = [];
            foreach ($registry->getResourceTemplates()->references as $template) {
                $resourceTemplates[] = [
                    'uriTemplate' => $template->uriTemplate,
                    'name' => $template->name,
                    'description' => $template->description,
                    'mimeType' => $template->mimeType,
                    'handler' => $this->formatHandler($registry->getResourceTemplate($template->uriTemplate)->handler),
                ];
            }

            $this->data['servers'][$server] = [
                'tools' => $tools,
                'prompts' => $prompts,
                'resources' => $resources,
                'resourceTemplates' => $resourceTemplates,
            ];
        }
    }

    /**
     * @return array<string, array{
     *     tools: array<array{name: string, description: ?string, inputSchema: array<mixed>, handler: string}>,
     *     prompts: array<array{name: string, description: ?string, arguments: array<mixed>, handler: string}>,
     *     resources: array<array{uri: string, name: string, description: ?string, mimeType: ?string, handler: string}>,
     *     resourceTemplates: array<array{uriTemplate: string, name: string, description: ?string, mimeType: ?string, handler: string}>,
     * }>
     */
    public function getServers(): array
    {
        return $this->data['servers'] ?? [];
    }

    public function getServerCount(): int
    {
        return \count($this->getServers());
    }

    public function getTotalCount(): int
    {
        $total = 0;
        foreach ($this->getServers() as $server) {
            $total += \count($server['tools']) + \count($server['prompts']) + \count($server['resources']) + \count($server['resourceTemplates']);
        }

        return $total;
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
