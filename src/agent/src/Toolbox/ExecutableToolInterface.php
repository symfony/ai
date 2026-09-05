<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Self-executing tool contract.
 *
 * A tool implementing this interface receives the raw tool call directly and
 * bypasses the reflection-based argument resolver in {@see Toolbox}. The
 * implementation is responsible for validating arguments against its own
 * schema before executing.
 *
 * This is the integration point for tools whose execution does not map to a
 * single PHP method signature, such as remote MCP server tools where the
 * argument shape is described by a JSON Schema returned by the server.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ExecutableToolInterface
{
    /**
     * Execute the tool described by $metadata for the given $toolCall.
     *
     * The return value is wrapped into a {@see ToolResult} by the toolbox; it
     * may be any value the platform can serialize.
     */
    public function execute(Tool $metadata, ToolCall $toolCall): mixed;
}
