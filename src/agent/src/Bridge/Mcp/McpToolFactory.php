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

use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Turns the tools of an MCP toolset into {@see Tool} definitions.
 *
 * Plugs into the agent's {@see \Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory} next to
 * {@see \Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory}, so local `#[AsTool]`
 * services and remote MCP servers end up in one toolbox.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpToolFactory implements ToolFactoryInterface
{
    public function getTool(object|string $reference): iterable
    {
        if (!$reference instanceof McpToolAdapter) {
            throw ToolException::invalidReference(\is_object($reference) ? $reference::class : $reference);
        }

        $prefix = $reference->getPrefix();

        foreach ($reference->getToolset()->getTools() as $remote) {
            $inputSchema = $remote->inputSchema;
            // A server without any parameters sends `properties: {}`, which the SDK keeps as an
            // object while the platform's JSON schema expects the empty list.
            if (isset($inputSchema['properties']) && $inputSchema['properties'] instanceof \stdClass) {
                $inputSchema['properties'] = [];
            }

            yield new Tool(
                new ExecutionReference(McpToolAdapter::class, $remote->name),
                $prefix.$remote->name,
                $remote->description ?? '',
                $inputSchema,
            );
        }
    }
}
