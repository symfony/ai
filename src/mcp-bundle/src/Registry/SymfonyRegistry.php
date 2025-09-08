<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Registry;

use Mcp\Capability\Discovery\Discoverer;
use Mcp\Capability\Prompt\IdentifierInterface as PromptIdentifierInterface;
use Mcp\Capability\Prompt\MetadataInterface as PromptMetadataInterface;
use Mcp\Capability\Prompt\PromptGetterInterface;
use Mcp\Capability\Registry;
use Mcp\Capability\Resource\IdentifierInterface as ResourceIdentifierInterface;
use Mcp\Capability\Resource\MetadataInterface as ResourceMetadataInterface;
use Mcp\Capability\Resource\ResourceReaderInterface;
use Mcp\Capability\Tool\IdentifierInterface;
use Mcp\Capability\Tool\MetadataInterface;
use Mcp\Capability\Tool\ToolExecutorInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\Resource;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Exception\InvalidArgumentException;

/**
 * Extended Registry that can register Symfony services as MCP tools.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class SymfonyRegistry extends Registry
{
    private ?Discoverer $discoverer = null;

    /**
     * @param array<string, mixed> $serverCapabilitiesConfig
     */
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly array $serverCapabilitiesConfig = [],
    ) {
        parent::__construct(logger: $this->logger);
        $this->discoverer = new Discoverer($this, $this->logger);
    }

    public function getCapabilities(): ServerCapabilities
    {
        $parentCapabilities = parent::getCapabilities();

        if (empty($this->serverCapabilitiesConfig)) {
            return $parentCapabilities;
        }

        return new ServerCapabilities(
            tools: $this->serverCapabilitiesConfig['tools'] ?? $parentCapabilities->tools,
            toolsListChanged: $this->serverCapabilitiesConfig['tools_list_changed'] ?? $parentCapabilities->toolsListChanged,
            resources: $this->serverCapabilitiesConfig['resources'] ?? $parentCapabilities->resources,
            resourcesSubscribe: $this->serverCapabilitiesConfig['resources_subscribe'] ?? $parentCapabilities->resourcesSubscribe,
            resourcesListChanged: $this->serverCapabilitiesConfig['resources_list_changed'] ?? $parentCapabilities->resourcesListChanged,
            prompts: $this->serverCapabilitiesConfig['prompts'] ?? $parentCapabilities->prompts,
            promptsListChanged: $this->serverCapabilitiesConfig['prompts_list_changed'] ?? $parentCapabilities->promptsListChanged,
            logging: $this->serverCapabilitiesConfig['logging'] ?? $parentCapabilities->logging,
            completions: $this->serverCapabilitiesConfig['completions'] ?? $parentCapabilities->completions,
            experimental: $this->serverCapabilitiesConfig['experimental'] ?? $parentCapabilities->experimental,
        );
    }

    /**
     * Discover MCP tools using attributes in the specified directories.
     */
    /**
     * @param array<string> $directories
     * @param array<string> $excludeDirs
     */
    public function discoverTools(string $basePath, array $directories, array $excludeDirs = []): void
    {
        $this->discoverer?->discover($basePath, $directories, $excludeDirs);
    }

    /**
     * Register a Symfony service that implements tool interfaces.
     */
    public function registerToolService(object $service): void
    {
        if (!$service instanceof IdentifierInterface) {
            throw new InvalidArgumentException('Tool service must implement IdentifierInterface');
        }

        $tool = new Tool(
            name: $service->getName(),
            description: $service instanceof MetadataInterface ? $service->getDescription() : null,
            inputSchema: $service instanceof MetadataInterface ? $service->getInputSchema() : null,
            annotations: null,
        );

        if (!$service instanceof ToolExecutorInterface) {
            throw new InvalidArgumentException('Service must implement ToolExecutorInterface');
        }

        // Register the tool with the service as the callable handler
        $this->registerTool($tool, [$service, 'call'], true);
    }

    /**
     * Register a Symfony service that implements prompt interfaces.
     */
    public function registerPromptService(object $service): void
    {
        if (!$service instanceof PromptIdentifierInterface) {
            throw new InvalidArgumentException('Prompt service must implement PromptIdentifierInterface');
        }

        $arguments = null;
        if ($service instanceof PromptMetadataInterface) {
            $rawArguments = $service->getArguments();
            if (!empty($rawArguments)) {
                // Convert raw arguments array to PromptArgument objects if needed
                $arguments = array_map(
                    fn(array $arg) => new PromptArgument(
                        name: $arg['name'],
                        description: $arg['description'] ?? null,
                        required: $arg['required'] ?? false
                    ),
                    $rawArguments
                );
            }
        }

        $prompt = new Prompt(
            name: $service->getName(),
            description: $service instanceof PromptMetadataInterface ? $service->getDescription() : null,
            arguments: $arguments,
        );

        if (!$service instanceof PromptGetterInterface) {
            throw new InvalidArgumentException('Service must implement PromptGetterInterface');
        }

        // Register the prompt with the service as the callable handler
        $this->registerPrompt($prompt, [$service, 'get'], []);
    }

    /**
     * Register a Symfony service that implements resource interfaces.
     */
    public function registerResourceService(object $service): void
    {
        if (!$service instanceof ResourceIdentifierInterface) {
            throw new InvalidArgumentException('Resource service must implement ResourceIdentifierInterface');
        }

        $resource = new Resource(
            uri: $service->getUri(),
            name: $service instanceof ResourceMetadataInterface ? $service->getName() : null,
            description: $service instanceof ResourceMetadataInterface ? $service->getDescription() : null,
            mimeType: $service instanceof ResourceMetadataInterface ? $service->getMimeType() : null,
        );

        if (!$service instanceof ResourceReaderInterface) {
            throw new InvalidArgumentException('Service must implement ResourceReaderInterface');
        }

        // Register the resource with the service as the callable handler
        $this->registerResource($resource, [$service, 'read'], true);
    }
}
