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

use Mcp\Capability\Registry;
use Mcp\Capability\Tool\IdentifierInterface;
use Mcp\Capability\Tool\MetadataInterface;
use Mcp\Capability\Tool\ToolExecutorInterface;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;

/**
 * Extended Registry that can register Symfony services as MCP tools.
 *
 * @author Assistant
 */
final class SymfonyRegistry extends Registry
{
    /**
     * Register a Symfony service that implements tool interfaces.
     */
    public function registerToolService(object $service): void
    {
        if (!$service instanceof IdentifierInterface) {
            throw new \InvalidArgumentException('Tool service must implement IdentifierInterface');
        }

        $tool = new Tool(
            name: $service->getName(),
            description: $service instanceof MetadataInterface ? $service->getDescription() : null,
            inputSchema: $service instanceof MetadataInterface ? $service->getInputSchema() : null,
        );

        // Create a wrapper callable that adapts the old interface to the new one
        $callable = function (CallToolRequest $request) use ($service): CallToolResult {
            if ($service instanceof ToolExecutorInterface) {
                return $service->call($request);
            }

            // For backward compatibility with old tools, we'll need to adapt
            // This assumes the old tool has a call method that expects arguments
            throw new \InvalidArgumentException('Service does not implement ToolExecutorInterface and cannot be adapted');
        };

        // Register the tool with the wrapper callable
        $this->registerTool($tool, $callable, true);
    }
}
