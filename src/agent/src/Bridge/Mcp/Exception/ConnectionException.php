<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp\Exception;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ConnectionException extends RuntimeException
{
    public static function failed(string $serverName, \Throwable $previous): self
    {
        return new self(\sprintf('Failed to connect to MCP server "%s": %s', $serverName, $previous->getMessage()), 0, $previous);
    }
}
