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
     * @param array<string, array{dirs: string[],includes: string[]}> $bridges
     * @param array<string, array<string, array{enabled: bool}>>      $disabledFeatures
     */
    public function __construct(
        private string $basePath,
        private array $bridges,
        private array $disabledFeatures,
        private Discoverer $discoverer,
        private LoggerInterface $logger,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        $allTools = [];
        $allResources = [];
        $allPrompts = [];
        $allResourceTemplates = [];

        foreach ($this->bridges as $packageName => $data) {
            $discoveryState = $this->discoverer->discover($this->basePath, $data['dirs']);

            foreach ($discoveryState->getTools() as $name => $tool) {
                if (!$this->isFeatureAllowed($packageName, $name)) {
                    $this->logger->debug('Excluding tool by feature filter', [
                        'package' => $packageName,
                        'tool' => $name,
                    ]);
                    continue;
                }

                $allTools[$name] = $tool;
            }

            foreach ($discoveryState->getResources() as $uri => $resource) {
                if (!$this->isFeatureAllowed($packageName, $uri)) {
                    $this->logger->debug('Excluding resource by feature filter', [
                        'package' => $packageName,
                        'resource' => $uri,
                    ]);
                    continue;
                }

                $allResources[$uri] = $resource;
            }

            foreach ($discoveryState->getPrompts() as $name => $prompt) {
                if (!$this->isFeatureAllowed($packageName, $name)) {
                    $this->logger->debug('Excluding prompt by feature filter', [
                        'package' => $packageName,
                        'prompt' => $name,
                    ]);
                    continue;
                }

                $allPrompts[$name] = $prompt;
            }

            foreach ($discoveryState->getResourceTemplates() as $uriTemplate => $template) {
                if (!$this->isFeatureAllowed($packageName, $uriTemplate)) {
                    $this->logger->debug('Excluding resource template by feature filter', [
                        'package' => $packageName,
                        'template' => $uriTemplate,
                    ]);
                    continue;
                }

                $allResourceTemplates[$uriTemplate] = $template;
            }
        }

        $filteredState = new DiscoveryState(
            $allTools,
            $allResources,
            $allPrompts,
            $allResourceTemplates,
        );

        $registry->setDiscoveryState($filteredState);

        $this->logger->info('Loaded filtered capabilities', [
            'tools' => \count($allTools),
            'resources' => \count($allResources),
            'prompts' => \count($allPrompts),
            'resourceTemplates' => \count($allResourceTemplates),
        ]);
    }

    private function isFeatureAllowed(string $packageName, string $feature): bool
    {
        $data = $this->disabledFeatures[$packageName][$feature] ?? [];

        return $data['enabled'] ?? true;
    }
}
