<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\MCP\Tools;

use Mcp\Capability\Tool\IdentifierInterface;
use Mcp\Capability\Tool\MetadataInterface;
use Mcp\Capability\Tool\ToolExecutorInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;

/**
 * @author Tom Hart <tom.hart.221@gmail.com>
 */
final class CurrentTimeTool implements IdentifierInterface, MetadataInterface, ToolExecutorInterface
{
    public function call(CallToolRequest $request): CallToolResult
    {
        $format = $request->arguments['format'] ?? 'Y-m-d H:i:s';

        $timeString = (new \DateTime('now', new \DateTimeZone('UTC')))->format($format);

        return new CallToolResult([
            new TextContent($timeString),
        ]);
    }

    public function getName(): string
    {
        return 'current-time';
    }

    public function getDescription(): string
    {
        return 'Returns the current time in UTC';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'format' => [
                    'type' => 'string',
                    'description' => 'The format of the time, e.g. "Y-m-d H:i:s"',
                    'default' => 'Y-m-d H:i:s',
                ],
            ],
            'required' => [],
        ];
    }
}
