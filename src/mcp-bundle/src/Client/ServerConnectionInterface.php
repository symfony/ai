<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Client;

use Mcp\Schema\Enum\LoggingLevel;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptReference;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceReference;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\CompletionCompleteResult;
use Mcp\Schema\Result\GetPromptResult;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourcesResult;
use Mcp\Schema\Result\ListResourceTemplatesResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Schema\Tool;

/**
 * One connection of a configured MCP client to one remote MCP server.
 *
 * The connection is opened on first use and closed on kernel reset, so callers never deal with
 * transports or with the SDK's explicit connect/disconnect lifecycle.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ServerConnectionInterface
{
    /**
     * The configured name of the remote server (mcp.clients.<client>.servers.<name>).
     */
    public function getName(): string;

    /**
     * The configured name of the client owning this connection (mcp.clients.<name>).
     */
    public function getClientName(): string;

    public function isConnected(): bool;

    /**
     * Closes the connection. Idempotent, and reconnects transparently on the next call.
     */
    public function disconnect(): void;

    public function getServerInfo(): ?Implementation;

    /**
     * The protocol revision negotiated with this server, or null before the first request.
     */
    public function getProtocolVersion(): ?ProtocolVersion;

    public function getInstructions(): ?string;

    public function ping(): void;

    public function listTools(?string $cursor = null): ListToolsResult;

    /**
     * Every tool the server advertises, following pagination to its end.
     *
     * @return list<Tool>
     */
    public function getTools(): array;

    /**
     * @param array<string, mixed>                                                    $arguments
     * @param (callable(float $progress, ?float $total, ?string $message): void)|null $onProgress
     */
    public function callTool(string $name, array $arguments = [], ?callable $onProgress = null): CallToolResult;

    public function listResources(?string $cursor = null): ListResourcesResult;

    /**
     * @return list<ResourceDefinition>
     */
    public function getResources(): array;

    /**
     * @param (callable(float $progress, ?float $total, ?string $message): void)|null $onProgress
     */
    public function readResource(string $uri, ?callable $onProgress = null): ReadResourceResult;

    public function listResourceTemplates(?string $cursor = null): ListResourceTemplatesResult;

    /**
     * @return list<ResourceTemplate>
     */
    public function getResourceTemplates(): array;

    public function listPrompts(?string $cursor = null): ListPromptsResult;

    /**
     * @return list<Prompt>
     */
    public function getPrompts(): array;

    /**
     * @param array<string, string>                                                   $arguments
     * @param (callable(float $progress, ?float $total, ?string $message): void)|null $onProgress
     */
    public function getPrompt(string $name, array $arguments = [], ?callable $onProgress = null): GetPromptResult;

    /**
     * Ask the server to complete one argument of a prompt or resource template.
     *
     * @param array{name: string, value: string} $argument
     */
    public function complete(PromptReference|ResourceReference $ref, array $argument): CompletionCompleteResult;

    public function setLoggingLevel(LoggingLevel $level): void;

    /**
     * Tell the server that this client's roots changed.
     */
    public function sendRootsListChanged(): void;
}
