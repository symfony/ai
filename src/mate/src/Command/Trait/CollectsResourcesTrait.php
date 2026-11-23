<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command\Trait;

use Symfony\AI\Mate\Discovery\CapabilityCollector;

/**
 * Flattens the resources and resource templates of all extensions into one list.
 *
 * @phpstan-type ResourceData array{
 *     type: 'resource'|'template',
 *     uri: string,
 *     name: string|null,
 *     description: string|null,
 *     mime_type: string|null,
 *     output_schema: array<string, mixed>|null,
 *     handler: string,
 *     parameters: list<string>,
 *     extension: string
 * }
 *
 * @internal
 */
trait CollectsResourcesTrait
{
    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     *
     * @return array<string, ResourceData> keyed by URI or URI template
     */
    private function collectResources(array $extensions, CapabilityCollector $collector): array
    {
        $all = [];
        foreach ($extensions as $extensionName => $extension) {
            $capabilities = $collector->collectCapabilities($extensionName, $extension);

            foreach ($capabilities['resources'] as $uri => $resource) {
                $all[$uri] = [
                    'type' => 'resource',
                    'uri' => $resource['uri'],
                    'name' => $resource['name'],
                    'description' => $resource['description'],
                    'mime_type' => $resource['mime_type'],
                    'output_schema' => $resource['output_schema'],
                    'handler' => $resource['handler'],
                    'parameters' => [],
                    'extension' => $extensionName,
                ];
            }

            foreach ($capabilities['resource_templates'] as $uriTemplate => $template) {
                $all[$uriTemplate] = [
                    'type' => 'template',
                    'uri' => $template['uri_template'],
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'mime_type' => $template['mime_type'],
                    'output_schema' => $template['output_schema'],
                    'handler' => $template['handler'],
                    'parameters' => $this->templateParameters($template['uri_template']),
                    'extension' => $extensionName,
                ];
            }
        }

        return $all;
    }

    /**
     * @return list<string>
     */
    private function templateParameters(string $uriTemplate): array
    {
        preg_match_all('/\{([^}]+)\}/', $uriTemplate, $matches);

        return $matches[1];
    }
}
