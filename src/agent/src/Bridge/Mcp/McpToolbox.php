<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp;

use Mcp\Schema\Content\AudioContent;
use Mcp\Schema\Content\BlobResourceContents;
use Mcp\Schema\Content\EmbeddedResource;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool as McpTool;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Bridge\Mcp\Exception\McpException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Toolbox that exposes tools from an MCP server.
 *
 * This adapter allows using tools from a remote MCP server
 * as if they were local Symfony AI tools.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class McpToolbox implements ToolboxInterface
{
    /** @var Tool[]|null */
    private ?array $tools = null;

    /** @var array<string, McpTool>|null */
    private ?array $mcpToolsIndex = null;

    /**
     * @param string[]|null $allowedTools List of tool names to expose (null = all tools)
     */
    public function __construct(
        private readonly McpClient $client,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?array $allowedTools = null,
    ) {
    }

    public function getTools(): array
    {
        if (null !== $this->tools) {
            return $this->tools;
        }

        $mcpTools = $this->client->listTools();
        $this->tools = [];
        $this->mcpToolsIndex = [];

        foreach ($mcpTools as $mcpTool) {
            // Filter by allowed tools if specified
            if (null !== $this->allowedTools && !\in_array($mcpTool->name, $this->allowedTools, true)) {
                continue;
            }

            $this->tools[] = $this->convertMcpTool($mcpTool);
            $this->mcpToolsIndex[$mcpTool->name] = $mcpTool;
        }

        return $this->tools;
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $this->ensureToolExists($toolCall);

        try {
            $result = $this->client->callTool($toolCall->getName(), $toolCall->getArguments());
            $content = $this->convertMcpContent($result);

            return new ToolResult($toolCall, $content);
        } catch (McpException $e) {
            throw ToolExecutionException::executionFailed($toolCall, $e);
        }
    }

    /**
     * Clear the cached tools list.
     *
     * Call this method to refresh the tools from the MCP server.
     */
    public function refresh(): void
    {
        $this->tools = null;
        $this->mcpToolsIndex = null;
    }

    /**
     * Convert an MCP tool definition to a Symfony AI Tool.
     */
    private function convertMcpTool(McpTool $mcpTool): Tool
    {
        // Create a placeholder ExecutionReference since MCP tools are executed remotely
        $reference = new ExecutionReference(self::class, $mcpTool->name);

        $schema = $mcpTool->inputSchema;
        $parameters = [
            'type' => 'object',
            'properties' => $schema['properties'] ?? [],
            'required' => $schema['required'] ?? [],
            'additionalProperties' => false,
        ];

        return new Tool(
            $reference,
            $mcpTool->name,
            $mcpTool->description ?? '',
            $parameters,
        );
    }

    /**
     * Convert MCP CallToolResult to Platform ContentInterface[].
     *
     * MCP returns content as an array of typed content blocks (TextContent, ImageContent, etc.).
     * This method converts them to Symfony AI Platform content types.
     *
     * @return ContentInterface[]
     */
    private function convertMcpContent(CallToolResult $result): array
    {
        $contents = [];

        foreach ($result->content as $content) {
            $converted = match (true) {
                $content instanceof TextContent => new Text($result->isError ? 'Error: '.$content->text : $content->text),
                $content instanceof ImageContent => new Image($this->decodeBase64($content->data), $content->mimeType),
                $content instanceof AudioContent => new Audio($this->decodeBase64($content->data), $content->mimeType),
                $content instanceof EmbeddedResource => $this->convertEmbeddedResource($content),
                default => $this->handleUnknownContent($content),
            };

            if (null !== $converted) {
                $contents[] = $converted;
            }
        }

        return $contents;
    }

    /**
     * Convert an MCP EmbeddedResource to the appropriate Platform ContentInterface.
     */
    private function convertEmbeddedResource(EmbeddedResource $resource): ContentInterface
    {
        $resourceContent = $resource->resource;

        if ($resourceContent instanceof TextResourceContents) {
            return new Text($resourceContent->text);
        }

        // BlobResourceContents - detect type from mimeType
        /** @var BlobResourceContents $resourceContent */
        $mimeType = $resourceContent->mimeType ?? 'application/octet-stream';
        $data = $this->decodeBase64($resourceContent->blob);

        return match (true) {
            str_starts_with($mimeType, 'image/') => new Image($data, $mimeType),
            str_starts_with($mimeType, 'audio/') => new Audio($data, $mimeType),
            default => new File($data, $mimeType),
        };
    }

    private function handleUnknownContent(mixed $content): null
    {
        $this->logger->warning('Unknown MCP content type received: {type}', [
            'type' => $content::class,
        ]);

        return null;
    }

    private function decodeBase64(string $data): string
    {
        $decoded = base64_decode($data, true);

        if (false === $decoded) {
            throw new McpException('Invalid base64 data received from MCP server.');
        }

        return $decoded;
    }

    private function ensureToolExists(ToolCall $toolCall): void
    {
        // Ensure tools are loaded
        $this->getTools();

        if (!isset($this->mcpToolsIndex[$toolCall->getName()])) {
            throw ToolNotFoundException::notFoundForToolCall($toolCall);
        }
    }
}
