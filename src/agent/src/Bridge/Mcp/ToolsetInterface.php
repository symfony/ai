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
 * The tools an agent draws from one remote MCP server, narrower than the protocol on purpose.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ToolsetInterface
{
    /**
     * Short name of the server, used for error messages and the tool-name prefix.
     */
    public function getName(): string;

    /**
     * Every tool the server advertises, pagination followed to its end.
     *
     * @return list<McpTool>
     */
    public function getTools(): array;

    /**
     * @param array<string, mixed> $arguments
     */
    public function callTool(string $name, array $arguments = []): CallToolResult;
}
