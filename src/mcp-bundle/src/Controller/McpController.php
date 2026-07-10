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
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class McpController
{
    /**
     * @param list<string>|false|null $allowedHosts Hostnames allowed by the DNS rebinding protection: null keeps the
     *                                              SDK default (localhost only), an array restricts it to those hosts,
     *                                              false disables the protection entirely
     */
    public function __construct(
        private readonly Server $server,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?LoggerInterface $logger = null,
        private readonly array|false|null $allowedHosts = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->resolveMiddleware(),
        );

        $psrResponse = $this->server->run($transport);
        $streamed = 'text/event-stream' === strtolower($psrResponse->getHeaderLine('Content-Type'));

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    /**
     * Returns null to keep the SDK secure defaults, or a tailored middleware stack when the DNS
     * rebinding protection needs to be disabled or restricted to custom hosts.
     *
     * @return list<MiddlewareInterface>|null
     */
    private function resolveMiddleware(): ?array
    {
        // Unset: keep the SDK secure defaults (DNS rebinding protection limited to localhost).
        if (null === $this->allowedHosts) {
            return null;
        }

        $middleware = [];
        foreach (StreamableHttpTransport::defaultMiddleware() as $defaultMiddleware) {
            if ($defaultMiddleware instanceof DnsRebindingProtectionMiddleware) {
                // false: disable the protection entirely to expose a public MCP server.
                if (false === $this->allowedHosts) {
                    continue;
                }

                // array: restrict the protection to the configured hosts.
                $defaultMiddleware = new DnsRebindingProtectionMiddleware($this->allowedHosts);
            }

            $middleware[] = $defaultMiddleware;
        }

        return $middleware;
    }
}
