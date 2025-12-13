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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Transport for communicating with MCP servers via SSE (Server-Sent Events).
 *
 * This transport implements the legacy MCP SSE protocol:
 * 1. Connect to /sse endpoint via GET to establish SSE connection
 * 2. Receive endpoint event with message URL and session ID
 * 3. Send requests via POST to the message endpoint
 * 4. Receive responses via the SSE connection
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
final class SseTransport implements TransportInterface
{
    private ?string $messageEndpoint = null;
    private ?string $baseUrl = null;
    private ?ResponseInterface $sseConnection = null;
    private string $sseBuffer = '';

    /**
     * @param string                $url     The MCP server SSE URL (e.g., https://example.com/sse)
     * @param array<string, string> $headers Additional HTTP headers (e.g., Authorization)
     * @param int                   $timeout Timeout in seconds for waiting for responses
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $url,
        private readonly array $headers = [],
        private readonly int $timeout = 30,
    ) {
    }

    public function connect(): void
    {
        // Parse base URL for relative endpoint resolution
        $parsedUrl = parse_url($this->url);
        $this->baseUrl = \sprintf('%s://%s', $parsedUrl['scheme'] ?? 'https', $parsedUrl['host'] ?? '');
        if (isset($parsedUrl['port'])) {
            $this->baseUrl .= ':'.$parsedUrl['port'];
        }

        try {
            // Open SSE connection (kept alive for receiving responses)
            $this->sseConnection = $this->httpClient->request('GET', $this->url, [
                'headers' => array_merge([
                    'Accept' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                ], $this->headers),
                'timeout' => 300, // Long timeout for SSE
            ]);

            $statusCode = $this->sseConnection->getStatusCode();
            if ($statusCode >= 400) {
                throw new ConnectionException(\sprintf('Failed to connect to SSE endpoint: HTTP %d', $statusCode));
            }

            // Read the endpoint event
            $this->messageEndpoint = $this->waitForEndpoint();

            if (null === $this->messageEndpoint) {
                throw new ConnectionException('No endpoint received from SSE connection.');
            }
        } catch (ConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException(\sprintf('Failed to establish SSE connection: "%s".', $e->getMessage()), 0, $e);
        }
    }

    public function request(array $data): array
    {
        if (null === $this->messageEndpoint || null === $this->sseConnection) {
            $this->connect();
        }

        $url = $this->resolveEndpointUrl($this->messageEndpoint);
        $requestId = $data['id'] ?? null;

        try {
            // Send POST request (fire and forget - response comes via SSE)
            $postResponse = $this->httpClient->request('POST', $url, [
                'headers' => array_merge([
                    'Content-Type' => 'application/json',
                ], $this->headers),
                'json' => $data,
            ]);

            $statusCode = $postResponse->getStatusCode();
            // 200, 202 Accepted are both valid
            if ($statusCode >= 400) {
                throw new ConnectionException(\sprintf('MCP server returned HTTP %d: "%s".', $statusCode, $postResponse->getContent(false)));
            }

            // Wait for response on SSE connection
            return $this->waitForResponse($requestId);
        } catch (ConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException(\sprintf('Failed to send request to MCP server: "%s".', $e->getMessage()), 0, $e);
        }
    }

    public function notify(array $data): void
    {
        if (null === $this->messageEndpoint) {
            $this->connect();
        }

        $url = $this->resolveEndpointUrl($this->messageEndpoint);

        try {
            $this->httpClient->request('POST', $url, [
                'headers' => array_merge([
                    'Content-Type' => 'application/json',
                ], $this->headers),
                'json' => $data,
            ]);
        } catch (\Throwable) {
            // Notifications are fire-and-forget
        }
    }

    public function disconnect(): void
    {
        $this->sseConnection = null;
        $this->messageEndpoint = null;
        $this->sseBuffer = '';
    }

    /**
     * Wait for the endpoint event on SSE connection.
     */
    private function waitForEndpoint(): ?string
    {
        $start = time();

        foreach ($this->httpClient->stream($this->sseConnection, $this->timeout) as $chunk) {
            if ($chunk->isTimeout()) {
                if (time() - $start > $this->timeout) {
                    break;
                }
                continue;
            }

            $this->sseBuffer .= $chunk->getContent();

            // Parse SSE events from buffer
            $endpoint = $this->parseEndpointFromBuffer();
            if (null !== $endpoint) {
                return $endpoint;
            }
        }

        return null;
    }

    /**
     * Wait for a JSON-RPC response matching the request ID.
     *
     * @return array<string, mixed>
     */
    private function waitForResponse(?int $requestId): array
    {
        $start = time();

        foreach ($this->httpClient->stream($this->sseConnection, $this->timeout) as $chunk) {
            if ($chunk->isTimeout()) {
                if (time() - $start > $this->timeout) {
                    throw new ConnectionException('Timeout waiting for SSE response.');
                }
                continue;
            }

            $this->sseBuffer .= $chunk->getContent();

            // Try to parse a response from buffer
            $response = $this->parseResponseFromBuffer($requestId);
            if (null !== $response) {
                return $response;
            }
        }

        throw new ConnectionException('SSE connection closed without response.');
    }

    /**
     * Parse endpoint from SSE buffer.
     */
    private function parseEndpointFromBuffer(): ?string
    {
        // Look for complete endpoint event
        if (!str_contains($this->sseBuffer, "\n\n") && !str_contains($this->sseBuffer, "\r\n\r\n")) {
            return null;
        }

        $lines = preg_split('/\r?\n/', $this->sseBuffer);
        $currentEvent = null;

        foreach ($lines as $i => $line) {
            if (str_starts_with($line, 'event: ')) {
                $currentEvent = trim(substr($line, 7));
            } elseif (str_starts_with($line, 'data: ') && 'endpoint' === $currentEvent) {
                $endpoint = trim(substr($line, 6));
                // Remove processed lines from buffer
                $this->sseBuffer = implode("\n", \array_slice($lines, $i + 1));

                return $endpoint;
            }
        }

        return null;
    }

    /**
     * Parse JSON-RPC response from SSE buffer.
     *
     * @return array<string, mixed>|null
     */
    private function parseResponseFromBuffer(?int $requestId): ?array
    {
        // Look for complete events (double newline)
        while (preg_match('/data: (.+?)(?:\r?\n\r?\n|\r?\nevent:)/s', $this->sseBuffer, $matches, \PREG_OFFSET_CAPTURE)) {
            $data = trim($matches[1][0]);
            $endPos = $matches[0][1] + \strlen($matches[0][0]);

            // Handle case where we matched "event:" - don't consume it
            if (str_ends_with($matches[0][0], 'event:')) {
                $endPos -= 6;
            }

            // Remove processed data from buffer
            $this->sseBuffer = substr($this->sseBuffer, $endPos);

            // Skip ping
            if ('ping' === $data) {
                continue;
            }

            $decoded = json_decode($data, true);
            if (null !== $decoded && isset($decoded['jsonrpc'])) {
                // Match by ID or return any response/error
                if (null === $requestId || (isset($decoded['id']) && $decoded['id'] === $requestId) || isset($decoded['error'])) {
                    return $decoded;
                }
            }
        }

        // Also check if buffer ends with a complete data line
        if (preg_match('/data: (.+?)$/s', $this->sseBuffer, $matches)) {
            $data = trim($matches[1]);
            if ('ping' !== $data) {
                $decoded = json_decode($data, true);
                if (null !== $decoded && isset($decoded['jsonrpc'])) {
                    if (null === $requestId || (isset($decoded['id']) && $decoded['id'] === $requestId) || isset($decoded['error'])) {
                        $this->sseBuffer = '';

                        return $decoded;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Resolve a potentially relative endpoint URL to an absolute URL.
     */
    private function resolveEndpointUrl(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        return $this->baseUrl.$endpoint;
    }
}
