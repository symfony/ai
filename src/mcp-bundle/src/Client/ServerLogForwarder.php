<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Client;

use Mcp\Schema\Notification\LoggingMessageNotification;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Writes the logging notifications a remote MCP server sends to the application's "mcp" channel.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ServerLogForwarder
{
    public function __construct(
        private readonly string $clientName,
        private readonly string $serverName,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(LoggingMessageNotification $notification): void
    {
        $this->logger->log($notification->level->value, $this->format($notification->data), [
            'mcp_client' => $this->clientName,
            'mcp_server' => $this->serverName,
            'mcp_logger' => $notification->logger,
        ]);
    }

    private function format(mixed $data): string
    {
        if (\is_string($data)) {
            return $data;
        }

        return json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }
}
