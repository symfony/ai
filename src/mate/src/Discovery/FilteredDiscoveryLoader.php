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
use Mcp\Capability\Discovery\DiscoveryState;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Create loaded that automatically discover MCP features.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class FilteredDiscoveryLoader implements LoaderInterface
{
    /**
     * @param array<string, array{dirs: string[],includes: string[]}> $extensions
     * @param array<string, array<string, array{enabled: bool}>>      $disabledFeatures
     */
    public function __construct(
        private string $basePath,
        private array $extensions,
        private array $disabledFeatures,
        private Discoverer $discoverer,
        private LoggerInterface $logger,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        $filteredState = new DiscoveryState();

        foreach ($this->extensions as $packageName => $data) {
            $discoveryState = $this->loadByExtension($packageName, $data);
            $filteredState = new DiscoveryState(
                array_merge($filteredState->getTools(), $discoveryState->getTools()),
                array_merge($filteredState->getResources(), $discoveryState->getResources()),
                array_merge($filteredState->getPrompts(), $discoveryState->getPrompts()),
                array_merge($filteredState->getResourceTemplates(), $discoveryState->getResourceTemplates()),
            );
        }

        $registry->setDiscoveryState($filteredState);

        $this->logger->info('Loaded filtered capabilities', [
            'tools' => \count($filteredState->getTools()),
            'resources' => \count($filteredState->getResources()),
            'prompts' => \count($filteredState->getPrompts()),
            'resourceTemplates' => \count($filteredState->getResourceTemplates()),
        ]);
    }

    /**
     * @param array{dirs: string[], includes: string[]} $extension
     */
    public function loadByExtension(string $extensionName, array $extension): DiscoveryState
    {
        $tools = [];
        $resources = [];
        $prompts = [];
        $resourceTemplates = [];

        $discoveryState = $this->discoverer->discover($this->basePath, $extension['dirs']);

        foreach ($discoveryState->getTools() as $name => $tool) {
            if (!$this->isFeatureAllowed($extensionName, $name)) {
                $this->logger->debug('Excluding tool by feature filter', [
                    'extension' => $extensionName,
                    'tool' => $name,
                ]);
                continue;
            }

            $tools[$name] = $tool;
        }

        foreach ($discoveryState->getResources() as $uri => $resource) {
            if (!$this->isFeatureAllowed($extensionName, $uri)) {
                $this->logger->debug('Excluding resource by feature filter', [
                    'extension' => $extensionName,
                    'resource' => $uri,
                ]);
                continue;
            }

            $resources[$uri] = $resource;
        }

        foreach ($discoveryState->getPrompts() as $name => $prompt) {
            if (!$this->isFeatureAllowed($extensionName, $name)) {
                $this->logger->debug('Excluding prompt by feature filter', [
                    'extension' => $extensionName,
                    'prompt' => $name,
                ]);
                continue;
            }

            $prompts[$name] = $prompt;
        }

        foreach ($discoveryState->getResourceTemplates() as $uriTemplate => $template) {
            if (!$this->isFeatureAllowed($extensionName, $uriTemplate)) {
                $this->logger->debug('Excluding resource template by feature filter', [
                    'extension' => $extensionName,
                    'template' => $uriTemplate,
                ]);
                continue;
            }

            $resourceTemplates[$uriTemplate] = $template;
        }

        return new DiscoveryState(
            $tools,
            $resources,
            $prompts,
            $resourceTemplates,
        );
    }

    private function isFeatureAllowed(string $extensionName, string $feature): bool
    {
        $data = $this->disabledFeatures[$extensionName][$feature] ?? [];

        return $data['enabled'] ?? true;
    }
}
