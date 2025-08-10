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
use Symfony\AI\McpSdk\Message\StreamableResponse;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Server\TransportInterface;

final readonly class Server
{
    public function __construct(
        private JsonRpcHandler $jsonRpcHandler,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function connect(TransportInterface $transport): void
    {
        $transport->initialize();
        $this->logger->info('Transport initialized');

        while ($transport->isConnected()) {
            foreach ($transport->receive() as $message) {
                if (null === $message) {
                    continue;
                }

                try {
                    $response = $this->jsonRpcHandler->process($message);
                    if (null === $response) {
                        continue;
                    }
                    if ($response instanceof StreamableResponse) {
                        foreach ($response->responses as $response) {
                            $transport->send($this->jsonRpcHandler->encodeResponse($response));
                        }
                    } else {
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

            usleep(1000);
        }

        $transport->close();
        $this->logger->info('Transport closed');
    }
}
