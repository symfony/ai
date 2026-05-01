<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Mcp;

use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Content\Content;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Tool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * Tool reference that encodes array/object results via {@see ResponseEncoder}
 * (TOON when available, JSON otherwise) for the MCP text content block, while
 * letting the SDK extract the raw value into MCP's `structuredContent`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MateToolReference extends ToolReference
{
    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    public function __construct(
        Tool $tool,
        \Closure|array|string $handler,
        bool $isManual = false,
    ) {
        parent::__construct($tool, $handler, $isManual);
    }

    /**
     * @return Content[]
     */
    public function formatResult(mixed $toolExecutionResult): array
    {
        if ($toolExecutionResult instanceof Content) {
            return [$toolExecutionResult];
        }

        if (\is_array($toolExecutionResult)) {
            $hasContent = false;
            $allContent = true;

            foreach ($toolExecutionResult as $item) {
                if ($item instanceof Content) {
                    $hasContent = true;
                } else {
                    $allContent = false;
                }
            }

            if ($hasContent && $allContent) {
                return $toolExecutionResult;
            }
        }

        if (\is_array($toolExecutionResult) || \is_object($toolExecutionResult)) {
            return [new TextContent(ResponseEncoder::encode($toolExecutionResult))];
        }

        return parent::formatResult($toolExecutionResult);
    }
}
