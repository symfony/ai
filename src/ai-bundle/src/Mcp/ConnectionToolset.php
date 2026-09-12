<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Mcp;

use Mcp\Schema\Result\CallToolResult;
use Symfony\AI\Agent\Bridge\Mcp\ToolsetInterface;
use Symfony\AI\McpBundle\Client\ServerConnectionInterface;

/**
 * The tools of a connection the MCP bundle owns, reused instead of opening a second one.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConnectionToolset implements ToolsetInterface
{
    public function __construct(
        private readonly ServerConnectionInterface $connection,
    ) {
    }

    public function getName(): string
    {
        return $this->connection->getName();
    }

    public function getTools(): array
    {
        return $this->connection->getTools();
    }

    public function callTool(string $name, array $arguments = []): CallToolResult
    {
        return $this->connection->callTool($name, $arguments);
    }
}
