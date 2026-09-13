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

use Mcp\Client;
use Mcp\Client\Transport\TransportInterface;
use Mcp\Exception\ExceptionInterface as McpExceptionInterface;
use Mcp\Schema\Enum\LoggingLevel;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\PromptReference;
use Mcp\Schema\ResourceReference;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\CompletionCompleteResult;
use Mcp\Schema\Result\GetPromptResult;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourcesResult;
use Mcp\Schema\Result\ListResourceTemplatesResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Result\ReadResourceResult;
use Symfony\AI\McpBundle\Exception\ConnectionException;
use Symfony\AI\McpBundle\Exception\ExceptionInterface;
use Symfony\AI\McpBundle\Exception\RemoteCallException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Connects lazily to a remote MCP server and forwards the SDK client's request surface.
 *
 * The SDK's {@see Client} throws unless `connect()` ran first, and a stdio transport spawns a child
 * process — neither should leak into application code. The connection therefore opens on the first
 * request and closes on kernel reset, so a long-running worker never carries a stale child process or
 * a dead HTTP session from one message into the next.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ServerConnection implements ServerConnectionInterface, ResetInterface
{
    private bool $connected = false;

    public function __construct(
        private readonly string $clientName,
        private readonly string $name,
        private readonly Client $client,
        private readonly TransportInterface $transport,
    ) {
    }

    public function __destruct()
    {
        try {
            $this->disconnect();
        } catch (\Throwable) {
            // Without this, PHP closes the stdio child's process handle at shutdown with a blocking wait.
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->client->isConnected();
    }

    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        // Cleared first: a failing close must not leave the connection looking usable.
        $this->connected = false;

        try {
            $this->client->disconnect();
        } catch (McpExceptionInterface $e) {
            throw ConnectionException::disconnectFailed($this->clientName, $this->name, $e);
        }
    }

    public function reset(): void
    {
        try {
            $this->disconnect();
        } catch (ExceptionInterface) {
            // Resetting the container must never fail because a remote server misbehaved.
        }
    }

    public function getServerInfo(): ?Implementation
    {
        $this->connect();

        return $this->client->getServerInfo();
    }

    public function getProtocolVersion(): ?ProtocolVersion
    {
        $this->connect();

        return $this->client->getProtocolVersion();
    }

    public function getInstructions(): ?string
    {
        $this->connect();

        return $this->client->getInstructions();
    }

    public function ping(): void
    {
        $this->call('ping', fn () => $this->client->ping());
    }

    public function listTools(?string $cursor = null): ListToolsResult
    {
        return $this->call('tools/list', fn (): ListToolsResult => $this->client->listTools($cursor));
    }

    public function getTools(): array
    {
        return $this->paginate(fn (?string $cursor): ListToolsResult => $this->listTools($cursor), 'tools');
    }

    public function callTool(string $name, array $arguments = [], ?callable $onProgress = null): CallToolResult
    {
        return $this->call('tools/call', fn (): CallToolResult => $this->client->callTool($name, $arguments, $onProgress));
    }

    public function listResources(?string $cursor = null): ListResourcesResult
    {
        return $this->call('resources/list', fn (): ListResourcesResult => $this->client->listResources($cursor));
    }

    public function getResources(): array
    {
        return $this->paginate(fn (?string $cursor): ListResourcesResult => $this->listResources($cursor), 'resources');
    }

    public function readResource(string $uri, ?callable $onProgress = null): ReadResourceResult
    {
        return $this->call('resources/read', fn (): ReadResourceResult => $this->client->readResource($uri, $onProgress));
    }

    public function listResourceTemplates(?string $cursor = null): ListResourceTemplatesResult
    {
        return $this->call('resources/templates/list', fn (): ListResourceTemplatesResult => $this->client->listResourceTemplates($cursor));
    }

    public function getResourceTemplates(): array
    {
        return $this->paginate(fn (?string $cursor): ListResourceTemplatesResult => $this->listResourceTemplates($cursor), 'resourceTemplates');
    }

    public function listPrompts(?string $cursor = null): ListPromptsResult
    {
        return $this->call('prompts/list', fn (): ListPromptsResult => $this->client->listPrompts($cursor));
    }

    public function getPrompts(): array
    {
        return $this->paginate(fn (?string $cursor): ListPromptsResult => $this->listPrompts($cursor), 'prompts');
    }

    public function getPrompt(string $name, array $arguments = [], ?callable $onProgress = null): GetPromptResult
    {
        return $this->call('prompts/get', fn (): GetPromptResult => $this->client->getPrompt($name, $arguments, $onProgress));
    }

    public function complete(PromptReference|ResourceReference $ref, array $argument): CompletionCompleteResult
    {
        return $this->call('completion/complete', fn (): CompletionCompleteResult => $this->client->complete($ref, $argument));
    }

    public function setLoggingLevel(LoggingLevel $level): void
    {
        $this->call('logging/setLevel', fn () => $this->client->setLoggingLevel($level));
    }

    public function sendRootsListChanged(): void
    {
        $this->call('notifications/roots/list_changed', fn () => $this->client->sendRootsListChanged());
    }

    /**
     * @template T of ListToolsResult|ListResourcesResult|ListResourceTemplatesResult|ListPromptsResult
     *
     * @param callable(?string): T $page
     *
     * @return list<mixed>
     */
    private function paginate(callable $page, string $property): array
    {
        $items = [];
        $cursor = null;

        do {
            $result = $page($cursor);
            foreach ($result->{$property} as $item) {
                $items[] = $item;
            }
            $cursor = $result->nextCursor;
        } while (null !== $cursor);

        return $items;
    }

    /**
     * @template T
     *
     * @param callable(): T $request
     *
     * @return T
     */
    private function call(string $operation, callable $request): mixed
    {
        $this->connect();

        try {
            return $request();
        } catch (McpExceptionInterface|\JsonException $e) {
            throw RemoteCallException::failed($this->clientName, $this->name, $operation, $e);
        }
    }

    private function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            $this->client->connect($this->transport);
        } catch (McpExceptionInterface $e) {
            throw ConnectionException::connectFailed($this->clientName, $this->name, $e);
        }

        $this->connected = true;
    }
}
