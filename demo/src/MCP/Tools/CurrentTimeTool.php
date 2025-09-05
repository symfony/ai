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

use Mcp\Capability\Tool\MetadataInterface;
use Mcp\Capability\Tool\ToolAnnotationsInterface;
use Mcp\Capability\Tool\ToolCall;
use Mcp\Capability\Tool\ToolCallResult;
use Mcp\Capability\Tool\ToolExecutorInterface;

/**
 * @author Tom Hart <tom.hart.221@gmail.com>
 */
class CurrentTimeTool implements MetadataInterface, ToolExecutorInterface
{
    public function call(ToolCall $input): ToolCallResult
    {
        $format = $input->arguments['format'] ?? 'Y-m-d H:i:s';

        return new ToolCallResult(
            (new \DateTime('now', new \DateTimeZone('UTC')))->format($format)
        );
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
            'required' => ['format'],
        ];
    }

    public function getOutputSchema(): ?array
    {
        return null;
    }

    public function getTitle(): ?string
    {
        return null;
    }

    public function getAnnotations(): ?ToolAnnotationsInterface
    {
        return null;
    }
}
