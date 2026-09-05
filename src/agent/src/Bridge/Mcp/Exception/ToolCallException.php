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
class ToolCallException extends RuntimeException
{
    public static function listFailed(string $serverName, \Throwable $previous): self
    {
        return new self(\sprintf('Failed to list tools on MCP server "%s": %s', $serverName, $previous->getMessage()), 0, $previous);
    }

    public static function callFailed(string $serverName, string $toolName, \Throwable $previous): self
    {
        return new self(\sprintf('Tool "%s" on MCP server "%s" failed: %s', $toolName, $serverName, $previous->getMessage()), 0, $previous);
    }

    public static function returnedError(string $serverName, string $toolName, string $detail): self
    {
        return new self(\sprintf('Tool "%s" on MCP server "%s" returned an error: %s', $toolName, $serverName, $detail));
    }
}
