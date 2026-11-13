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

use Mcp\Server\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class McpController
{
    public function __construct(
        private readonly Server $server,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly MiddlewareFactory $middlewareFactory,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?int $maxBodyBytes = null,
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $transport = new StreamableHttpTransport(
            $this->identifyPrincipal($this->httpMessageFactory->createRequest($request)),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->middlewareFactory->create(),
            maxBodyBytes: $this->maxBodyBytes ?? StreamableHttpTransport::DEFAULT_MAX_BODY_BYTES,
        );

        $psrResponse = $this->server->run($transport);
        $streamed = 'text/event-stream' === strtolower($psrResponse->getHeaderLine('Content-Type'));

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    private function identifyPrincipal(ServerRequestInterface $request): ServerRequestInterface
    {
        if (null !== $request->getAttribute(StreamableHttpTransport::PRINCIPAL_ATTRIBUTE)) {
            return $request;
        }

        $identifier = $this->tokenStorage?->getToken()?->getUserIdentifier();
        if (null === $identifier || '' === $identifier) {
            return $request;
        }

        return $request->withAttribute(StreamableHttpTransport::PRINCIPAL_ATTRIBUTE, $identifier);
    }
}
