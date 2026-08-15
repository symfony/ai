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
 * Thrown when a request a configured MCP client sent to a remote server failed.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class RemoteCallException extends RuntimeException
{
    public static function failed(string $client, string $server, string $operation, \Throwable $previous): self
    {
        return new self(\sprintf('The "%s" request of MCP client "%s" to server "%s" failed: %s', $operation, $client, $server, $previous->getMessage()), 0, $previous);
    }
}
