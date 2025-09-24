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
use Psr\Log\NullLogger;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class McpController
{
    public function __construct(
        private Server $server,
        private HttpMessageFactoryInterface $psrHttpFactory,
        private HttpFoundationFactoryInterface $httpFoundationFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $psrRequest = $this->psrHttpFactory->createRequest($request);

        $transport = new StreamableHttpTransport(
            $psrRequest,
            $this->responseFactory,
            $this->streamFactory,
            $this->logger ?? new NullLogger(),
        );

        $this->server->connect($transport);
        $psrResponse = $transport->listen();

        return $this->httpFoundationFactory->createResponse($psrResponse);
    }
}
