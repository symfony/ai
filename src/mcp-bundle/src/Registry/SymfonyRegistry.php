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
 * @author Oskar Stark <oskarstark@googlemail.com>
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

        if (!$service instanceof ToolExecutorInterface) {
            throw new \InvalidArgumentException('Service must implement ToolExecutorInterface');
        }

        // Register the tool with the service as the callable handler
        $this->registerTool($tool, [$service, 'call'], true);
    }
}
