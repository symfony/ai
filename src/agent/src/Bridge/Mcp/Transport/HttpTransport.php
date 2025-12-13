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

use Symfony\AI\Agent\Bridge\Mcp\Exception\ConnectionException;

/**
 * Transport for communicating with MCP servers via HTTP.
 *
 * This transport sends JSON-RPC messages over HTTP POST requests.
 * Supports authorization headers for OAuth/API key authentication.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class HttpTransport extends AbstractHttpTransport
{
    /**
     * Parse response - handles both JSON and SSE (Streamable HTTP) formats.
     *
     * @return array<string, mixed>
     */
    protected function parseResponse(string $content): array
    {
        // First try to parse as plain JSON
        $decoded = json_decode($content, true);
        if (null !== $decoded && \is_array($decoded) && isset($decoded['jsonrpc'])) {
            return $decoded;
        }

        // Otherwise parse as SSE (Streamable HTTP format)
        $lines = explode("\n", $content);
        $eventData = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);

                // Skip ping events
                if ('ping' === $data) {
                    continue;
                }

                $decoded = json_decode($data, true);
                if (null !== $decoded && isset($decoded['jsonrpc'])) {
                    // Return the first JSON-RPC message (response or error)
                    if (isset($decoded['id']) || isset($decoded['error'])) {
                        return $decoded;
                    }
                    $eventData = $decoded;
                }
            }
        }

        if (null !== $eventData) {
            return $eventData;
        }

        throw new ConnectionException('No valid JSON-RPC response found in HTTP response.');
    }
}
