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

use Mcp\Server\Stateless\StatelessProtocol;
use Mcp\Server\Transport\StatelessHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a server built for the modern (2026-07-28) lifecycle.
 *
 * The difference from {@see McpController} is the whole point of that revision:
 * there is no `initialize` handshake and no session, so the transport takes the
 * dispatcher rather than one request, and nothing has to be carried between
 * calls. Any worker can answer any request.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class StatelessMcpController
{
    public function __construct(
        private readonly StatelessProtocol $protocol,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly MiddlewareFactory $middlewareFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $transport = new StatelessHttpTransport(
            $this->protocol,
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger ?? new \Psr\Log\NullLogger(),
            middleware: $this->middlewareFactory->create(),
        );

        $psrResponse = $transport->handle($this->httpMessageFactory->createRequest($request));
        $streamed = str_contains(strtolower($psrResponse->getHeaderLine('Content-Type')), 'text/event-stream');

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }
}
