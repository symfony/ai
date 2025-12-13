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

use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\GetPromptResult;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourcesResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\Tool;
use Symfony\AI\Agent\Bridge\Mcp\Exception\McpException;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ProtocolException;
use Symfony\AI\Agent\Bridge\Mcp\Transport\TransportInterface;

/**
 * Client for communicating with MCP (Model Context Protocol) servers.
 *
 * This client handles the MCP protocol including initialization handshake,
 * tool listing and execution, resource access, and prompt retrieval.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class McpClient
{
    private const PROTOCOL_VERSION = '2024-11-05';

    private int $requestId = 0;
    private bool $initialized = false;
    private ?InitializeResult $initializeResult = null;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $clientName = 'symfony-ai',
        private readonly string $clientVersion = '1.0.0',
    ) {
    }

    public function __destruct()
    {
        if ($this->initialized) {
            $this->close();
        }
    }

    /**
     * Initialize the connection to the MCP server.
     *
     * This performs the MCP handshake:
     * 1. Send initialize request with client capabilities
     * 2. Receive server capabilities
     * 3. Send initialized notification
     */
    public function initialize(): InitializeResult
    {
        if ($this->initialized && null !== $this->initializeResult) {
            return $this->initializeResult;
        }

        $this->transport->connect();

        $response = $this->request('initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => new \stdClass(),
            'clientInfo' => [
                'name' => $this->clientName,
                'version' => $this->clientVersion,
            ],
        ]);

        $this->initializeResult = InitializeResult::fromArray($response['result'] ?? []);

        // Send initialized notification
        $this->notify('notifications/initialized');

        $this->initialized = true;

        return $this->initializeResult;
    }

    /**
     * Close the connection to the MCP server.
     */
    public function close(): void
    {
        $this->transport->disconnect();
        $this->initialized = false;
        $this->initializeResult = null;
    }

    /**
     * Get the server capabilities returned during initialization.
     */
    public function getServerCapabilities(): ?ServerCapabilities
    {
        return $this->initializeResult?->capabilities;
    }

    /**
     * List available tools from the MCP server.
     *
     * @return list<Tool>
     */
    public function listTools(): array
    {
        $this->ensureInitialized();

        return $this->listPaginated('tools/list', fn (array $data) => ListToolsResult::fromArray($data), 'tools');
    }

    /**
     * Call a tool on the MCP server.
     *
     * @param array<string, mixed> $arguments The tool arguments
     */
    public function callTool(string $name, array $arguments = []): CallToolResult
    {
        $this->ensureInitialized();

        // Filter out null values from arguments
        $arguments = array_filter($arguments, fn ($value) => null !== $value);

        $response = $this->request('tools/call', [
            'name' => $name,
            'arguments' => [] === $arguments ? new \stdClass() : $arguments,
        ]);

        return CallToolResult::fromArray($response['result'] ?? ['content' => []]);
    }

    /**
     * List available resources from the MCP server.
     *
     * @return list<\Mcp\Schema\Resource>
     */
    public function listResources(): array
    {
        $this->ensureInitialized();

        return $this->listPaginated('resources/list', fn (array $data) => ListResourcesResult::fromArray($data), 'resources');
    }

    /**
     * Read a resource from the MCP server.
     */
    public function readResource(string $uri): ReadResourceResult
    {
        $this->ensureInitialized();

        $response = $this->request('resources/read', [
            'uri' => $uri,
        ]);

        return ReadResourceResult::fromArray($response['result'] ?? ['contents' => []]);
    }

    /**
     * List available prompts from the MCP server.
     *
     * @return list<Prompt>
     */
    public function listPrompts(): array
    {
        $this->ensureInitialized();

        return $this->listPaginated('prompts/list', fn (array $data) => ListPromptsResult::fromArray($data), 'prompts');
    }

    /**
     * Get a prompt from the MCP server.
     *
     * @param array<string, mixed> $arguments The prompt arguments
     */
    public function getPrompt(string $name, array $arguments = []): GetPromptResult
    {
        $this->ensureInitialized();

        $response = $this->request('prompts/get', [
            'name' => $name,
            'arguments' => [] === $arguments ? new \stdClass() : $arguments,
        ]);

        return GetPromptResult::fromArray($response['result'] ?? ['messages' => []]);
    }

    /**
     * Ping the MCP server.
     */
    public function ping(): void
    {
        $this->ensureInitialized();

        $this->request('ping');
    }

    /**
     * Generic paginated list method to reduce duplication.
     *
     * @template T
     *
     * @param callable(array<string, mixed>): T $resultFactory
     *
     * @return array<mixed>
     */
    private function listPaginated(string $method, callable $resultFactory, string $itemsKey): array
    {
        $items = [];
        $cursor = null;

        do {
            $params = [];
            if (null !== $cursor) {
                $params['cursor'] = $cursor;
            }

            $response = $this->request($method, $params);
            $result = $resultFactory($response['result'] ?? [$itemsKey => []]);

            foreach ($result->$itemsKey as $item) {
                $items[] = $item;
            }

            $cursor = $result->nextCursor;
        } while (null !== $cursor);

        return $items;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function request(string $method, array $params = []): array
    {
        $id = ++$this->requestId;

        $data = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => [] === $params ? new \stdClass() : $params,
        ];

        $response = $this->transport->request($data);

        if (isset($response['id']) && $response['id'] !== $id) {
            throw new McpException(\sprintf('Response ID mismatch: expected %d, got "%s".', $id, $response['id']));
        }

        if (isset($response['error'])) {
            $error = $response['error'];
            throw new ProtocolException($error['code'] ?? 0, $error['message'] ?? 'Unknown error', $error['data'] ?? null);
        }

        return $response;
    }

    private function notify(string $method): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => new \stdClass(),
        ];

        $this->transport->notify($data);
    }

    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }
    }
}
