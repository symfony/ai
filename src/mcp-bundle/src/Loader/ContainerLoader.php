<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Loader;

use Mcp\Capability\Completion\EnumCompletionProvider;
use Mcp\Capability\Completion\ListCompletionProvider;
use Mcp\Capability\Completion\ProviderInterface;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\InvalidArgumentException;
use Mcp\Schema\Annotations;
use Mcp\Schema\Icon;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\Resource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;

/**
 * @phpstan-type ToolEntry array{
 *     class: class-string,
 *     method: string,
 *     name: string,
 *     description: ?string,
 *     inputSchema: array<string, mixed>,
 *     outputSchema: ?array<string, mixed>,
 *     annotations: ?array<string, mixed>,
 *     icons: ?list<array{src: string, mimeType?: string, sizes?: string[]}>,
 *     meta: ?array<string, mixed>,
 * }
 * @phpstan-type ResourceEntry array{
 *     class: class-string,
 *     method: string,
 *     uri: string,
 *     name: ?string,
 *     description: ?string,
 *     mimeType: ?string,
 *     size: ?int,
 *     annotations: ?array{audience?: string[], priority?: float},
 *     icons: ?list<array{src: string, mimeType?: string, sizes?: string[]}>,
 *     meta: ?array<string, mixed>,
 * }
 * @phpstan-type PromptEntry array{
 *     class: class-string,
 *     method: string,
 *     name: string,
 *     description: ?string,
 *     arguments: list<array{name: string, description: ?string, required: bool}>,
 *     icons: ?list<array{src: string, mimeType?: string, sizes?: string[]}>,
 *     meta: ?array<string, mixed>,
 *     completionProviders: array<string, array{type: string, values?: list<string|int|float>, class?: class-string}>,
 * }
 * @phpstan-type ResourceTemplateEntry array{
 *     class: class-string,
 *     method: string,
 *     uriTemplate: string,
 *     name: ?string,
 *     description: ?string,
 *     mimeType: ?string,
 *     annotations: ?array{audience?: string[], priority?: float},
 *     meta: ?array<string, mixed>,
 *     completionProviders: array<string, array{type: string, values?: list<string|int|float>, class?: class-string}>,
 * }
 */
final class ContainerLoader implements LoaderInterface
{
    /**
     * @param ToolEntry[]             $tools
     * @param ResourceEntry[]         $resources
     * @param PromptEntry[]           $prompts
     * @param ResourceTemplateEntry[] $resourceTemplates
     */
    public function __construct(
        private readonly array $tools = [],
        private readonly array $resources = [],
        private readonly array $prompts = [],
        private readonly array $resourceTemplates = [],
    ) {
    }

    #[\Override]
    public function load(RegistryInterface $registry): void
    {
        foreach ($this->tools as $data) {
            $tool = new Tool(
                $data['name'],
                $data['inputSchema'],
                $data['description'],
                null !== $data['annotations'] ? ToolAnnotations::fromArray($data['annotations']) : null,
                null !== $data['icons'] ? $this->buildIcons($data['icons']) : null,
                $data['meta'],
                $data['outputSchema'],
            );

            $registry->registerTool($tool, [$data['class'], $data['method']], true);
        }

        foreach ($this->resources as $data) {
            $resource = new Resource(
                $data['uri'],
                $data['name'],
                $data['description'],
                $data['mimeType'],
                null !== $data['annotations'] ? Annotations::fromArray($data['annotations']) : null,
                $data['size'],
                null !== $data['icons'] ? $this->buildIcons($data['icons']) : null,
                $data['meta'],
            );
            $registry->registerResource($resource, [$data['class'], $data['method']], true);
        }

        foreach ($this->prompts as $data) {
            $arguments = array_map(
                static fn (array $arg) => new PromptArgument($arg['name'], $arg['description'], $arg['required']),
                $data['arguments'],
            );
            $prompt = new Prompt(
                $data['name'],
                $data['description'],
                $arguments,
                null !== $data['icons'] ? $this->buildIcons($data['icons']) : null,
                $data['meta'],
            );
            $completionProviders = $this->buildCompletionProviders($data['completionProviders']);
            $registry->registerPrompt($prompt, [$data['class'], $data['method']], $completionProviders, true);
        }

        foreach ($this->resourceTemplates as $data) {
            $template = new ResourceTemplate(
                $data['uriTemplate'],
                $data['name'],
                $data['description'],
                $data['mimeType'],
                null !== $data['annotations'] ? Annotations::fromArray($data['annotations']) : null,
                $data['meta'],
            );
            $completionProviders = $this->buildCompletionProviders($data['completionProviders']);
            $registry->registerResourceTemplate($template, [$data['class'], $data['method']], $completionProviders, true);
        }
    }

    /**
     * @param list<array{src: string, mimeType?: string, sizes?: string[]}> $icons
     *
     * @return Icon[]
     */
    private function buildIcons(array $icons): array
    {
        return array_map(
            static fn (array $icon) => Icon::fromArray($icon),
            $icons,
        );
    }

    /**
     * @param array<string, array{type: string, values?: list<string|int|float>, class?: class-string}> $providers
     *
     * @return array<string, ProviderInterface|class-string>
     */
    private function buildCompletionProviders(array $providers): array
    {
        return array_map(static function ($data) {
            return match ($data['type']) {
                'list' => new ListCompletionProvider($data['values'] ?? []),
                'enum' => new EnumCompletionProvider($data['class']),
                'class' => $data['class'],
                default => throw new InvalidArgumentException(\sprintf('Unknown completion provider type "%s".', $data['type'])),
            };
        }, $providers);
    }
}
