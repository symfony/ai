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

use Mcp\Schema\Content\Content;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Bridge\Mcp\Exception\ToolCallException;
use Symfony\AI\Agent\Toolbox\AbstractToolbox;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The tools of one remote MCP server, as a toolbox.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpToolbox extends AbstractToolbox
{
    /**
     * @var Tool[]
     */
    private array $toolsMetadata;

    /**
     * @param string $prefix Prefixes every remote tool name, defaults to "<server>_"
     */
    public function __construct(
        private readonly ToolsetInterface $toolset,
        private readonly string $prefix = '',
        LoggerInterface $logger = new NullLogger(),
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($logger, $eventDispatcher);
    }

    public function getTools(): array
    {
        if (isset($this->toolsMetadata)) {
            return $this->toolsMetadata;
        }

        try {
            $remoteTools = $this->toolset->getTools();
        } catch (\Exception $e) {
            // A server we cannot reach must not take the agent's other tools down with it. Nothing
            // is memoized here on purpose: the next listing asks the server again.
            $this->logger->error(\sprintf('Failed to list the tools of MCP server "%s", it contributes none to this run.', $this->toolset->getName()), ['exception' => $e]);

            return [];
        }

        $prefix = '' !== $this->prefix ? $this->prefix : $this->toolset->getName().'_';

        $toolsMetadata = [];
        foreach ($remoteTools as $remote) {
            $inputSchema = $remote->inputSchema;
            // `properties: {}` would reach the platform as `[]`, which is not a JSON object.
            if ($this->declaresNoArguments($inputSchema)) {
                $inputSchema = null;
            }

            $toolsMetadata[] = new Tool(
                new ExecutionReference(self::class, $remote->name),
                $prefix.$remote->name,
                $remote->description ?? '',
                $inputSchema,
            );
        }

        return $this->toolsMetadata = $toolsMetadata;
    }

    protected function getExecutable(Tool $metadata): object
    {
        return $this;
    }

    /**
     * Forwarded as sent: the server validates against the schema it published.
     *
     * @return array<string, mixed>
     */
    protected function resolveArguments(object $tool, Tool $metadata, ToolCall $toolCall): array
    {
        return $toolCall->getArguments();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function invoke(object $tool, Tool $metadata, array $arguments): mixed
    {
        $remoteName = $metadata->getReference()->getMethod();

        $result = $this->toolset->callTool($remoteName, $arguments);

        $payload = $this->renderResult($result);

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
     * Structured output replaces the text block mirroring it, but never content that block cannot carry:
     * the spec only asks a server to repeat the serialized JSON as text, images and resources stay unique.
     *
     * Returns the structured value as sent, the rendering of the content blocks, or
     * `array{structuredContent: mixed, content: list<Content>}` when both carry something.
     */
    private function renderResult(CallToolResult $result): mixed
    {
        $structured = $result->structuredContent;

        // A client decodes `structuredContent: {}` into an empty array: nothing to say, so let the text speak.
        if (null === $structured || [] === $structured) {
            return $this->renderContent($result->content);
        }

        $nonText = array_values(array_filter(
            $result->content,
            static fn (Content $item): bool => !$item instanceof TextContent,
        ));

        if ([] === $nonText) {
            return $structured;
        }

        return [
            'structuredContent' => $structured,
            'content' => $nonText,
        ];
    }

    /**
     * The SDK turns an empty `properties` object into a `stdClass`.
     *
     * @param array<string, mixed> $inputSchema
     */
    private function declaresNoArguments(array $inputSchema): bool
    {
        $properties = $inputSchema['properties'] ?? null;

        if ($properties instanceof \stdClass) {
            $properties = (array) $properties;
        }

        return [] === $properties;
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
                // The SDK types `text` as mixed, a server may put structured data there.
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
