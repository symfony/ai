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

/**
 * Collects capabilities from a discovered extension and flattens them into plain arrays
 * for the listing/inspection commands.
 *
 * @phpstan-type Capabilities array{
 *     tools: array<string, array{
 *         name: string,
 *         description: string|null,
 *         handler: string,
 *         input_schema: array<string, mixed>|null
 *     }>,
 *     resources: array<string, array{
 *         uri: string,
 *         name: string|null,
 *         description: string|null,
 *         handler: string,
 *         mime_type: string|null
 *     }>,
 *     prompts: array<string, array{
 *         name: string,
 *         description: string|null,
 *         handler: string,
 *         arguments: array<mixed>|null
 *     }>,
 *     resource_templates: array<string, array{
 *         uri_template: string,
 *         name: string|null,
 *         description: string|null,
 *         handler: string
 *     }>
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CapabilityCollector
{
    public function __construct(
        private CapabilityRegistry $registry,
    ) {
    }

    /**
     * @param array{dirs: string[], includes: string[]} $extension
     *
     * @return Capabilities
     */
    public function collectCapabilities(string $extensionName, array $extension): array
    {
        $capabilities = $this->registry->capabilitiesForExtension($extensionName, $extension);

        $tools = [];
        foreach ($capabilities->getTools() as $name => $tool) {
            $tools[$name] = [
                'name' => $tool->name,
                'description' => $tool->description,
                'handler' => $tool->handlerLabel(),
                'input_schema' => $tool->inputSchema,
            ];
        }

        $resources = [];
        foreach ($capabilities->getResources() as $uri => $resource) {
            $resources[$uri] = [
                'uri' => $resource->uri,
                'name' => $resource->name,
                'description' => $resource->description,
                'handler' => $resource->handlerLabel(),
                'mime_type' => $resource->mimeType,
            ];
        }

        $resourceTemplates = [];
        foreach ($capabilities->getResourceTemplates() as $uriTemplate => $template) {
            $resourceTemplates[$uriTemplate] = [
                'uri_template' => $template->uriTemplate,
                'name' => $template->name,
                'description' => $template->description,
                'handler' => $template->handlerLabel(),
            ];
        }

        return [
            'tools' => $tools,
            'resources' => $resources,
            'prompts' => [],
            'resource_templates' => $resourceTemplates,
        ];
    }
}
