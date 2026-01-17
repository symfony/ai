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

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Security\Exception\InsufficientScopeException;
use Symfony\AI\McpBundle\Security\ScopeCheckerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class McpController
{
    public function __construct(
        private readonly Server $server,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ScopeCheckerInterface $scopeChecker = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($violation = $this->scopeChecker?->check($request)) {
            return $this->createForbiddenResponse($request, $violation);
        }

        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
        );

        return $this->httpFoundationFactory->createResponse(
            $this->server->run($transport),
        );
    }

    private function createForbiddenResponse(Request $request, InsufficientScopeException $exception): Response
    {
        $scheme = $request->headers->get('X-Forwarded-Proto', $request->getScheme());
        $host = $request->headers->get('X-Forwarded-Host', $request->getHttpHost());
        $baseUrl = $scheme.'://'.$host;
        $resourceMetadata = \sprintf('%s/.well-known/oauth-protected-resource', $baseUrl);

        // RFC 6750 Section 3.1: WWW-Authenticate with error and scope
        $header = \sprintf(
            'Bearer resource_metadata="%s", error="insufficient_scope", scope="%s"',
            $resourceMetadata,
            $exception->getScopeString()
        );

        $response = new Response(null, Response::HTTP_FORBIDDEN);
        $response->headers->set('WWW-Authenticate', $header);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
