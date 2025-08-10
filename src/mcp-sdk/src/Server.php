<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\McpSdk\Exception\TransportNotConnectedException;
use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Server\KeepAliveSessionInterface;
use Symfony\AI\McpSdk\Server\PendingResponse;
use Symfony\AI\McpSdk\Server\PendingResponseBag;
use Symfony\AI\McpSdk\Server\TransportInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class Server
{
    private ?TransportInterface $transport = null;

    private PendingResponseBag $pendingResponses;

    public function __construct(
        private JsonRpcHandler $jsonRpcHandler,
        private ClockInterface $clock,
        private ?KeepAliveSessionInterface $keepAliveSession = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->pendingResponses = new PendingResponseBag($clock, new \DateInterval('PT30S'));
    }

    public function connect(TransportInterface $transport): void
    {
        $this->transport = $transport;

        $transport->initialize();
        $this->logger->info('Transport initialized');

        $this->keepAliveSession?->start();

        while ($transport->isConnected()) {
            foreach ($transport->receive() as $message) {
                if (null === $message) {
                    continue;
                }

                try {
                    foreach ($this->jsonRpcHandler->process($message) as $response) {
                        if (null === $response) {
                            continue;
                        }

                        if ($response instanceof Response || $response instanceof Error) {
                            if ($this->pendingResponses->resolve($response)) {
                                continue;
                            }

                            $this->logger->warning(\sprintf('No handler found for response id "%s".', $response->id), ['response' => $response]);
                            continue;
                        }

                        $transport->send($response);
                    }
                } catch (\JsonException $e) {
                    $this->logger->error('Failed to encode response to JSON', [
                        'message' => $message,
                        'exception' => $e,
                    ]);
                    continue;
                }
            }

            $this->pendingResponses->gc(function (PendingResponse $pendingResponse, Error $error): void {
                $this->logger->warning('Pending response timed out', ['pendingResponse' => $pendingResponse, 'error' => $error]);
            });

            $this->keepAliveSession?->tick(function (): void {
                $id = (string) Uuid::v4();

                $this->sendRequest(new Request($id, 'ping', []), function (Response|Error $response): void {
                    // Per MCP spec, ping errors should terminate the connection, but some clients
                    // don't handle this correctly. We may want to consider adding a strict mode with
                    // strict error handling.
                    if ($response instanceof Error) {
                        $this->logger->warning('KeepAlive ping returned error response', ['error' => $response]);
                    }
                });
            });

            $this->clock->sleep(0.001);
        }

        $this->keepAliveSession?->stop();
        $transport->close();
        $this->logger->info('Transport closed');
    }

    /**
     * @throws \JsonException When JSON encoding fails
     */
    public function sendRequest(Request $request, ?\Closure $callback = null): void
    {
        if (null === $this->transport) {
            throw new TransportNotConnectedException();
        }

        $this->logger->info('Sending request', ['request' => $request]);

        if ([] === $request->params) {
            $encodedRequest = json_encode($request, \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT);
        } else {
            $encodedRequest = json_encode($request, \JSON_THROW_ON_ERROR);
        }

        $this->transport->send($encodedRequest);

        $this->pendingResponses->add(new PendingResponse($request->id, $this->clock->now(), $callback));
    }
}
