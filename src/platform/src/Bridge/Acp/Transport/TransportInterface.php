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

/**
 * Transport interface for ACP communication.
 */
interface TransportInterface
{
    /**
     * Starts the transport connection.
     */
    public function start(): void;

    /**
     * Sends a message through the transport.
     *
     * @param array<string, mixed> $message
     */
    public function send(array $message): void;

    /**
     * Reads the next message from the transport.
     *
     * @return array<string, mixed>
     */
    public function readNextMessage(): array;

    /**
     * Closes the transport connection.
     */
    public function close(): void;

    /**
     * Checks if the transport is currently running.
     */
    public function isRunning(): bool;
}
