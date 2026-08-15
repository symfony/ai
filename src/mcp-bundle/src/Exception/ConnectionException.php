<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Exception;

/**
 * Thrown when a configured MCP client cannot open or close a connection to a remote server.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ConnectionException extends RuntimeException
{
    public static function connectFailed(string $client, string $server, \Throwable $previous): self
    {
        return new self(\sprintf('Failed to connect MCP client "%s" to server "%s": %s', $client, $server, $previous->getMessage()), 0, $previous);
    }

    public static function disconnectFailed(string $client, string $server, \Throwable $previous): self
    {
        return new self(\sprintf('Failed to disconnect MCP client "%s" from server "%s": %s', $client, $server, $previous->getMessage()), 0, $previous);
    }
}
