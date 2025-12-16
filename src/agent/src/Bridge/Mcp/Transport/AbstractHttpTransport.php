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

/**
 * Abstract base class for HTTP-based MCP transports.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
abstract class AbstractHttpTransport implements TransportInterface
{
    protected ?string $sessionId = null;

    /**
     * @param string                $url     The MCP server URL
     * @param array<string, string> $headers Additional HTTP headers (e.g., Authorization)
     * @param int                   $timeout Timeout in seconds for requests
     */
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly string $url,
        protected readonly array $headers = [],
        protected readonly int $timeout = 30,
    ) {
    }

    public function connect(): void
    {
        // HTTP is stateless, nothing to do here
        // Session ID will be set from server response if needed
    }

    public function request(array $data): array
    {
        $headers = $this->buildHeaders();

        try {
            $response = $this->httpClient->request('POST', $this->url, [
                'headers' => $headers,
                'json' => $data,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new ConnectionException(\sprintf('MCP server returned HTTP %d: "%s".', $statusCode, $response->getContent(false)));
            }

            // Capture session ID from response headers
            $responseHeaders = $response->getHeaders();
            if (isset($responseHeaders['mcp-session-id'][0])) {
                $this->sessionId = $responseHeaders['mcp-session-id'][0];
            }

            return $this->parseResponse($response->getContent());
        } catch (ConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException(\sprintf('Failed to communicate with MCP server: "%s".', $e->getMessage()), 0, $e);
        }
    }

    public function notify(array $data): void
    {
        $headers = $this->buildHeaders();

        try {
            $this->httpClient->request('POST', $this->url, [
                'headers' => $headers,
                'json' => $data,
            ]);
        } catch (\Throwable) {
            // Notifications are fire-and-forget
        }
    }

    public function disconnect(): void
    {
        if (null === $this->sessionId) {
            return;
        }

        // Send DELETE request to end session
        try {
            $headers = array_merge([
                'Mcp-Session-Id' => $this->sessionId,
            ], $this->headers);

            $this->httpClient->request('DELETE', $this->url, [
                'headers' => $headers,
            ]);
        } catch (\Throwable) {
            // Ignore errors on disconnect
        }

        $this->sessionId = null;
    }

    /**
     * Parse the response content.
     *
     * @return array<string, mixed>
     */
    abstract protected function parseResponse(string $content): array;

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ], $this->headers);

        if (null !== $this->sessionId) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        return $headers;
    }
}
