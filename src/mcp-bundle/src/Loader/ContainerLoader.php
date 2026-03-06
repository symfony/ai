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

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Icon;
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
    }

    private function buildIcons(array $icons): array
    {
        return array_map(
            static fn (array $icon) => Icon::fromArray($icon),
            $icons,
        );
    }
}
