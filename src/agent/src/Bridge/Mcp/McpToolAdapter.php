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

use Mcp\Schema\Content\TextContent;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Toolbox\ExecutableToolInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Puts one MCP toolset into a toolbox as a single executable target.
 *
 * One instance stands for the N remote tools a server advertises: the toolbox holds it
 * once, and every {@see Tool} that {@see McpToolFactory} derived from that toolset points
 * back at it, carrying the remote tool name in its execution reference.
 *
 * The tool-name prefix lives here rather than on the toolset, because it exists to keep
 * two servers that both advertise a `search` tool apart within one toolbox - a question
 * about this agent's tools, not about the remote server.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpToolAdapter implements ExecutableToolInterface
{
    public function __construct(
        private readonly ToolsetInterface $toolset,
        private readonly string $prefix = '',
    ) {
    }

    public function getToolset(): ToolsetInterface
    {
        return $this->toolset;
    }

    public function getPrefix(): string
    {
        return '' !== $this->prefix ? $this->prefix : $this->toolset->getName().'_';
    }

    public function execute(Tool $metadata, ToolCall $toolCall): mixed
    {
        $remoteName = $metadata->getReference()->getMethod();

        $result = $this->toolset->callTool($remoteName, $toolCall->getArguments());

        if (null !== $result->structuredContent) {
            $payload = $result->structuredContent;
        } else {
            $payload = $this->renderContent($result->content);
        }

        if ($result->isError) {
            if (\is_string($payload)) {
                $detail = $payload;
            } else {
                $encoded = json_encode($payload, \JSON_UNESCAPED_SLASHES);
                $detail = false !== $encoded ? $encoded : 'unknown error';
            }

            throw ToolCallException::returnedError($this->toolset->getName(), $remoteName, $detail);
        }

        return $payload;
    }

    /**
     * @param list<object> $content
     *
     * @return string|array{text: string, content: list<object>}
     */
    private function renderContent(array $content): string|array
    {
        $textChunks = [];
        $other = [];

        foreach ($content as $item) {
            if ($item instanceof TextContent) {
                // The SDK types `text` as mixed, since a server is free to put structured data there.
                if (\is_string($item->text)) {
                    $textChunks[] = $item->text;
                } else {
                    $encoded = json_encode($item->text, \JSON_UNESCAPED_SLASHES);
                    $textChunks[] = false !== $encoded ? $encoded : '';
                }

                continue;
            }

            $other[] = $item;
        }

        if ([] === $other) {
            return implode("\n", $textChunks);
        }

        return [
            'text' => implode("\n", $textChunks),
            'content' => $content,
        ];
    }
}
