<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * OAuth 2.0 Protected Resource Metadata endpoint (RFC 9728).
 *
 * This controller exposes the metadata required by MCP clients to discover
 * the authorization server(s) for this MCP server.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9728
 * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization
 */
final class OAuthMetadataController
{
    /**
     * @param list<string> $authorizationServers
     * @param list<string> $scopesSupported
     */
    public function __construct(
        private readonly array $authorizationServers,
        private readonly ?string $resource,
        private readonly string $mcpPath,
        private readonly array $scopesSupported,
    ) {
    }

    /**
     * RFC 9728: OAuth 2.0 Protected Resource Metadata.
     *
     * Tells MCP clients where to find the authorization server(s).
     */
    public function protectedResource(Request $request): JsonResponse
    {
        $baseUrl = $this->getBaseUrl($request);
        $resource = $this->resource ?? $baseUrl.$this->mcpPath;

        $metadata = [
            'resource' => $resource,
            'authorization_servers' => $this->authorizationServers ?: [$baseUrl],
            'bearer_methods_supported' => ['header'],
        ];

        if ($this->scopesSupported) {
            $metadata['scopes_supported'] = $this->scopesSupported;
        }

        return new JsonResponse($metadata);
    }

    private function getBaseUrl(Request $request): string
    {
        $scheme = $request->headers->get('X-Forwarded-Proto', $request->getScheme());
        $host = $request->headers->get('X-Forwarded-Host', $request->getHttpHost());

        return $scheme.'://'.$host;
    }
}