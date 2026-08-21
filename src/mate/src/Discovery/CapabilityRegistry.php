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

use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\Model\DiscoveredCapabilities;
use Symfony\AI\Mate\Discovery\Model\ResourceDefinition;
use Symfony\AI\Mate\Discovery\Model\ResourceTemplateDefinition;
use Symfony\AI\Mate\Discovery\Model\ToolDefinition;

/**
 * Central access point to the capabilities discovered across all configured extensions.
 *
 * Discovery results are memoized per extension, feature filtering is applied from the
 * `mate.disabled_features` configuration, and lookup helpers resolve tools and resources
 * by name/URI for the `tools:call` and `resources:read` commands.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CapabilityRegistry
{
    /**
     * @var array<string, DiscoveredCapabilities>
     */
    private array $cache = [];

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     * @param array<string, array<string, array{enabled: bool}>>       $disabledFeatures
     */
    public function __construct(
        private string $rootDir,
        private array $extensions,
        private array $disabledFeatures,
        private ReflectionDiscoverer $discoverer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Discovers and feature-filters the capabilities of a single extension.
     *
     * @param array{dirs: string[], includes: string[]} $extension
     */
    public function capabilitiesForExtension(string $extensionName, array $extension): DiscoveredCapabilities
    {
        $cacheKey = $extensionName.':'.md5(serialize($extension));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $discovered = $this->discoverer->discover($this->rootDir, $extension['dirs']);

        $tools = [];
        foreach ($discovered->getTools() as $name => $tool) {
            if ($this->isFeatureAllowed($extensionName, $name)) {
                $tools[$name] = $tool;
            } else {
                $this->logger->debug('Excluding tool by feature filter', ['extension' => $extensionName, 'tool' => $name]);
            }
        }

        $resources = [];
        foreach ($discovered->getResources() as $uri => $resource) {
            if ($this->isFeatureAllowed($extensionName, $uri)) {
                $resources[$uri] = $resource;
            } else {
                $this->logger->debug('Excluding resource by feature filter', ['extension' => $extensionName, 'resource' => $uri]);
            }
        }

        $resourceTemplates = [];
        foreach ($discovered->getResourceTemplates() as $uriTemplate => $template) {
            if ($this->isFeatureAllowed($extensionName, $uriTemplate)) {
                $resourceTemplates[$uriTemplate] = $template;
            } else {
                $this->logger->debug('Excluding resource template by feature filter', ['extension' => $extensionName, 'template' => $uriTemplate]);
            }
        }

        return $this->cache[$cacheKey] = new DiscoveredCapabilities($tools, $resources, $resourceTemplates);
    }

    public function findTool(string $name): ?ToolDefinition
    {
        foreach ($this->extensions as $extensionName => $extension) {
            $tools = $this->capabilitiesForExtension($extensionName, $extension)->getTools();
            if (isset($tools[$name])) {
                return $tools[$name];
            }
        }

        return null;
    }

    public function findResource(string $uri): ?ResourceDefinition
    {
        foreach ($this->extensions as $extensionName => $extension) {
            $resources = $this->capabilitiesForExtension($extensionName, $extension)->getResources();
            if (isset($resources[$uri])) {
                return $resources[$uri];
            }
        }

        return null;
    }

    /**
     * Matches a URI against every registered resource template.
     *
     * @return array{template: ResourceTemplateDefinition, variables: array<string, string>}|null
     */
    public function matchResourceTemplate(string $uri): ?array
    {
        foreach ($this->extensions as $extensionName => $extension) {
            foreach ($this->capabilitiesForExtension($extensionName, $extension)->getResourceTemplates() as $template) {
                $variables = $template->extractVariables($uri);
                if (null !== $variables) {
                    return ['template' => $template, 'variables' => $variables];
                }
            }
        }

        return null;
    }

    private function isFeatureAllowed(string $extensionName, string $feature): bool
    {
        $data = $this->disabledFeatures[$extensionName][$feature] ?? [];

        return $data['enabled'] ?? true;
    }
}
