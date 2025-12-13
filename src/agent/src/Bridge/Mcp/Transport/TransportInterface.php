<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp\Transport;

/**
 * Interface for MCP transport implementations.
 *
 * Transports handle the low-level communication with MCP servers,
 * whether via stdio (local processes) or HTTP (remote servers).
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
interface TransportInterface
{
    /**
     * Establish connection to the MCP server.
     */
    public function connect(): void;

    /**
     * Send a request and receive the response.
     *
     * @param array<string, mixed> $data JSON-RPC message to send
     *
     * @return array<string, mixed> JSON-RPC response
     */
    public function request(array $data): array;

    /**
     * Send a notification (no response expected).
     *
     * @param array<string, mixed> $data JSON-RPC notification to send
     */
    public function notify(array $data): void;

    /**
     * Close the connection to the MCP server.
     */
    public function disconnect(): void;
}
