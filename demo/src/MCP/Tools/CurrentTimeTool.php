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

use DateTime;
use DateTimeZone;
use Symfony\AI\McpSdk\Capability\Tool\MetadataInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class CurrentTimeTool implements MetadataInterface, ToolExecutorInterface
{
    public function call(ToolCall $input): ToolCallResult
    {
        $format = $input->arguments['format'] ?? 'Y-m-d H:i:s';

        return new ToolCallResult(
            (new DateTime('now', new DateTimeZone('UTC')))->format($format)
        );
    }

    public function getName(): string
    {
        return 'now-time';
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
}
