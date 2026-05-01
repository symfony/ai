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

use Mcp\Capability\Discovery\DiscovererInterface;
use Mcp\Capability\Discovery\DiscoveryState;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Mcp\Attribute\StructuredOutput;
use Symfony\AI\Mate\Mcp\MateToolReference;

/**
 * Create loaded that automatically discover MCP features.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class FilteredDiscoveryLoader implements LoaderInterface
{
    private OutputSchemaGenerator $outputSchemaGenerator;

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     * @param array<string, array<string, array{enabled: bool}>>       $disabledFeatures
     */
    public function __construct(
        private string $rootDir,
        private array $extensions,
        private array $disabledFeatures,
        private DiscovererInterface $discoverer,
        private LoggerInterface $logger,
    ) {
        $this->outputSchemaGenerator = new OutputSchemaGenerator();
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

        $discoveryState = $this->discoverer->discover($this->rootDir, $extension['dirs']);

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

        $enrichedTools = [];
        foreach ($tools as $name => $toolRef) {
            $enrichedTools[$name] = $this->wrapToolReference($toolRef);
        }

        return new DiscoveryState(
            $enrichedTools,
            $resources,
            $prompts,
            $resourceTemplates,
        );
    }

    public function isFeatureAllowed(string $extensionName, string $feature): bool
    {
        $data = $this->disabledFeatures[$extensionName][$feature] ?? [];

        return $data['enabled'] ?? true;
    }

    private function wrapToolReference(ToolReference $toolRef): ToolReference
    {
        $method = $this->resolveHandlerMethod($toolRef);
        $structured = null !== $method && [] !== $method->getAttributes(StructuredOutput::class);
        $tool = $structured && null !== $method ? $this->addOutputSchema($toolRef->tool, $method) : $toolRef->tool;

        return new MateToolReference($tool, $toolRef->handler, $toolRef->isManual, $structured);
    }

    private function resolveHandlerMethod(ToolReference $toolRef): ?\ReflectionMethod
    {
        $handler = $toolRef->handler;

        if (!\is_array($handler) || 2 !== \count($handler)) {
            return null;
        }

        [$className, $methodName] = $handler;

        if (\is_object($className)) {
            $className = $className::class;
        }

        if (!\is_string($className) || !\is_string($methodName)) {
            return null;
        }

        try {
            return new \ReflectionMethod($className, $methodName);
        } catch (\ReflectionException) {
            return null;
        }
    }

    private function addOutputSchema(Tool $tool, \ReflectionMethod $method): Tool
    {
        $outputSchema = $this->outputSchemaGenerator->generate($method);

        if (null === $outputSchema || 'object' !== ($outputSchema['type'] ?? null)) {
            return $tool;
        }

        return new Tool(
            $tool->name,
            $tool->inputSchema,
            $tool->description,
            $tool->annotations,
            $tool->icons,
            $tool->meta,
            $outputSchema,
        );
    }
}
