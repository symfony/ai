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
 * The tools of a connection the MCP bundle configured, offered to an agent.
 *
 * The MCP bundle already owns the connection to a remote server - it connects on first use
 * and disconnects on kernel reset - so an agent reuses that connection instead of opening a
 * second one to the same server, which for a stdio transport would mean a second child
 * process. The agent only ever draws tools from it; the rest of the connection's surface
 * stays with the bundle.
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
