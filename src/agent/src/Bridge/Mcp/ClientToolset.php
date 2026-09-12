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

use Mcp\Client;
use Mcp\Client\Transport\TransportInterface;
use Mcp\Exception\ConnectionException as McpConnectionException;
use Mcp\Exception\ExceptionInterface as McpSdkExceptionInterface;
use Mcp\Schema\Result\CallToolResult;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ConnectionException;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;

/**
 * A toolset reached through the SDK's own MCP {@see Client}, connected on first use.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ClientToolset implements ToolsetInterface
{
    private bool $connected = false;

    public function __construct(
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
            // Otherwise PHP closes the stdio child's process handle at shutdown with a blocking wait.
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTools(): array
    {
        $this->connect();

        $tools = [];
        $cursor = null;

        do {
            try {
                $page = $this->client->listTools($cursor);
            } catch (McpSdkExceptionInterface $e) {
                $this->dropLostConnection($e);

                throw ToolCallException::listFailed($this->name, $e);
            }

            foreach ($page->tools as $tool) {
                $tools[] = $tool;
            }

            $cursor = $page->nextCursor;
        } while (null !== $cursor);

        return $tools;
    }

    public function callTool(string $name, array $arguments = []): CallToolResult
    {
        $this->connect();

        try {
            return $this->client->callTool($name, $arguments);
        } catch (McpSdkExceptionInterface $e) {
            $this->dropLostConnection($e);

            throw ToolCallException::callFailed($this->name, $name, $e);
        }
    }

    /**
     * Closes the connection. Idempotent, and reconnects transparently on the next call.
     */
    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        // Cleared first: a failing close must not leave the connection looking usable.
        $this->connected = false;

        $this->client->disconnect();
    }

    /**
     * A transport that died must not keep looking connected: {@see connect()} would skip reopening it,
     * and every later call would fail against it. An error the server itself sent back leaves the
     * connection intact - tearing it down would respawn a stdio child over a mistyped argument.
     */
    private function dropLostConnection(McpSdkExceptionInterface $e): void
    {
        if (!$e instanceof McpConnectionException) {
            return;
        }

        try {
            $this->disconnect();
        } catch (\Throwable) {
            // The connection is gone either way, and a failing close must not mask what killed it.
        }
    }

    private function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            $this->client->connect($this->transport);
        } catch (McpSdkExceptionInterface $e) {
            throw ConnectionException::failed($this->name, $e);
        }

        $this->connected = true;
    }
}
