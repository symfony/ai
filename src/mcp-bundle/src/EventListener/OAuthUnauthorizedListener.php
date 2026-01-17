<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds WWW-Authenticate header to 401/403 responses on the MCP endpoint.
 *
 * - 401 Unauthorized: RFC 9728 Section 5.1 - tells clients where to find
 *   the protected resource metadata for authentication.
 * - 403 Forbidden: RFC 6750 Section 3.1 - indicates insufficient scope.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9728#section-5.1
 * @see https://datatracker.ietf.org/doc/html/rfc6750#section-3.1
 * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization
 */
final class OAuthUnauthorizedListener
{
    public function __construct(
        private readonly string $mcpPath,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();

        // Only handle 401/403 responses on MCP endpoints
        if (!\in_array($statusCode, [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], true)) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), $this->mcpPath)) {
            return;
        }

        if ($response->headers->has('WWW-Authenticate')) {
            return;
        }

        $baseUrl = $this->getBaseUrl($request);
        $resourceMetadata = \sprintf('%s/.well-known/oauth-protected-resource', $baseUrl);

        $header = Response::HTTP_UNAUTHORIZED === $statusCode
            ? \sprintf('Bearer resource_metadata="%s"', $resourceMetadata)
            : \sprintf('Bearer resource_metadata="%s", error="insufficient_scope"', $resourceMetadata);

        $response->headers->set('WWW-Authenticate', $header);
    }

    private function getBaseUrl(Request $request): string
    {
        $scheme = $request->headers->get('X-Forwarded-Proto', $request->getScheme());
        $host = $request->headers->get('X-Forwarded-Host', $request->getHttpHost());

        return $scheme.'://'.$host;
    }
}
