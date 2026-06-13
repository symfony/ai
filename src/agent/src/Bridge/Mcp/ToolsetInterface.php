<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool as McpTool;

/**
 * The tools an agent draws from one remote MCP server.
 *
 * An agent is not an MCP client: it never asks a server for a prompt or reads one of its
 * resources, it only needs the tools the server offers and a way to call them. This
 * contract is therefore deliberately narrower than the protocol - it is a toolset that
 * happens to live behind an MCP connection, not a model of the server itself.
 *
 * The bridge ships {@see ClientToolset} on top of the SDK's own client. A Symfony
 * application usually has a connected server at hand already - the MCP bundle's
 * `mcp.client.<client>.server.<server>` service - and adapts that one instead of opening a
 * second connection to the same server.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolsetInterface
{
    /**
     * The short name of the remote server these tools come from, used in error messages and
     * to derive the tool-name prefix.
     */
    public function getName(): string;

    /**
     * Every tool the server advertises, following pagination to its end.
     *
     * Implementations report an unreachable server by throwing; the toolbox surfaces that
     * to the agent like any other failing tool source.
     *
     * @return list<McpTool>
     */
    public function getTools(): array;

    /**
     * @param array<string, mixed> $arguments
     */
    public function callTool(string $name, array $arguments = []): CallToolResult;
}
