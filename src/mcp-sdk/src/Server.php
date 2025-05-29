<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk;

use PhpLlm\McpSdk\Server\JsonRpcHandler;
use PhpLlm\McpSdk\Server\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
                } catch (\JsonException $e) {
                    $this->logger->error('Failed to process message', [
                        'message' => $message,
                        'exception' => $e,
                    ]);
                    continue;
                }

                if (null === $response) {
                    continue;
                }

                $transport->send($response);
            }

            usleep(1000);
        }

        $transport->close();
        $this->logger->info('Transport closed');
    }
}
