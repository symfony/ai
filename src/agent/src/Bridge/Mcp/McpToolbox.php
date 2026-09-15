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
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
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

    private ?\DateTimeImmutable $retryListingAt = null;

    /**
     * @param string $prefix     Prefixes every remote tool name, defaults to "<server>_"
     * @param int    $retryAfter Seconds a server whose tools could not be listed is skipped before it is asked again
     */
    public function __construct(
        private readonly ToolsetInterface $toolset,
        private readonly string $prefix = '',
        LoggerInterface $logger = new NullLogger(),
        ?EventDispatcherInterface $eventDispatcher = null,
        private readonly int $retryAfter = 60,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
        parent::__construct($logger, $eventDispatcher);
    }

    public function getTools(): array
    {
        if (isset($this->toolsMetadata)) {
            return $this->toolsMetadata;
        }

        // Finding a server down can take the client's whole connect timeout, paid again on every listing otherwise.
        if (null !== $this->retryListingAt && $this->clock->now() < $this->retryListingAt) {
            return [];
        }

        try {
            $remoteTools = $this->toolset->getTools();
        } catch (\Exception $e) {
            // A server we cannot reach must not take the agent's other tools down with it. The failure is
            // not memoized for good: once the delay elapsed, the next listing asks the server again.
            $this->retryListingAt = $this->clock->now()->modify(\sprintf('+%d seconds', $this->retryAfter));
            $this->logger->error(\sprintf('Failed to list the tools of MCP server "%s", it contributes none for the next %d seconds.', $this->toolset->getName(), $this->retryAfter), ['exception' => $e]);

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
     * Structured output replaces only the text blocks mirroring it as serialized JSON, as the spec asks a
     * server to send: a human-readable message, images and resources carry something of their own.
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

        $normalized = $this->normalize($structured);
        $remaining = array_values(array_filter(
            $result->content,
            fn (Content $item): bool => !$this->mirrors($item, $normalized),
        ));

        if ([] === $remaining) {
            return $structured;
        }

        return [
            'structuredContent' => $structured,
            'content' => $remaining,
        ];
    }

    /**
     * Decoded the way the client decodes `structuredContent`, and compared strictly: telling a mirror
     * apart from a message that merely resembles it only ever costs a duplicate, never the message.
     */
    private function mirrors(Content $item, mixed $normalizedStructured): bool
    {
        if (!$item instanceof TextContent || !\is_string($item->text)) {
            return false;
        }

        // Invalid JSON decodes to null, which a non-empty structured payload never equals.
        return $this->normalize(json_decode($item->text, true)) === $normalizedStructured;
    }

    /**
     * Sorts JSON objects by key, recursively, as their serialization may order members differently.
     */
    private function normalize(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $value = array_map($this->normalize(...), $value);

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
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
