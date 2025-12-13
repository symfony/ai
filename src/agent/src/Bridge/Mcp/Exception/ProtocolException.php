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
 * Exception thrown when MCP server returns a JSON-RPC error.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
class ProtocolException extends McpException
{
    public function __construct(
        private readonly int $errorCode,
        string $message,
        private readonly mixed $errorData = null,
    ) {
        parent::__construct(\sprintf('MCP protocol error %d: %s', $errorCode, $message));
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorData(): mixed
    {
        return $this->errorData;
    }
}
