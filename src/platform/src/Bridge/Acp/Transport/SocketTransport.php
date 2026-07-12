<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Transport;

use Amp\Socket\Socket;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Acp\Exception\TransportException;

final class SocketTransport implements TransportInterface
{
    private ?Socket $socket = null;
    private string $buffer = '';
    private bool $running = false;

    public function __construct(
        private readonly string $address,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $this->logger->info('Connecting to ACP socket.', ['address' => $this->address]);

        try {
            $this->socket = \Amp\Socket\connect($this->address);
        } catch (\Throwable $e) {
            throw new TransportException(\sprintf('Failed to connect to ACP socket "%s".', $this->address), 0, $e);
        }

        $this->running = true;
    }

    public function send(array $message): void
    {
        if (!$this->running || null === $this->socket) {
            throw new TransportException('ACP socket is not connected.');
        }

        try {
            $payload = json_encode($message, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
            $this->socket->write($payload);
            $this->logger->debug('ACP request sent.', ['payload' => $message]);
        } catch (\Throwable $e) {
            throw new TransportException('Failed to write to ACP socket.', 0, $e);
        }
    }

    public function readNextMessage(): array
    {
        if (!$this->running || null === $this->socket) {
            throw new TransportException('ACP socket is not connected.');
        }

        while (true) {
            if (str_contains($this->buffer, "\n")) {
                $parts = explode("\n", $this->buffer, 2);
                $line = trim($parts[0]);
                $this->buffer = $parts[1];

                if ('' === $line) {
                    continue;
                }

                $decoded = json_decode($line, true);
                if (!\is_array($decoded)) {
                    throw new TransportException('ACP returned malformed JSON.');
                }

                $this->logger->debug('ACP response received.', ['payload' => $decoded]);

                return $decoded;
            }

            $chunk = $this->socket->read();
            if (null === $chunk) {
                throw new TransportException('ACP socket closed unexpectedly.');
            }

            $this->buffer .= $chunk;
        }
    }

    public function close(): void
    {
        if (!$this->running) {
            return;
        }

        $this->socket?->close();
        $this->socket = null;
        $this->buffer = '';
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
