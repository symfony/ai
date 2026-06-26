<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * Result listing the tools exposed by a hosted MCP server, reported server-side
 * by the model (e.g. the OpenAI Responses `mcp_list_tools` output item).
 *
 * @phpstan-type McpTool array{
 *     name: string,
 *     description?: string|null,
 *     input_schema?: array<string, mixed>,
 *     annotations?: array<string, mixed>|null,
 * }
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class McpListToolsResult extends BaseResult
{
    /**
     * @param list<McpTool> $tools
     */
    public function __construct(
        private readonly string $serverLabel,
        private readonly array $tools = [],
        private readonly ?string $id = null,
    ) {
    }

    /**
     * @return list<McpTool>
     */
    public function getContent(): array
    {
        return $this->tools;
    }

    public function getServerLabel(): string
    {
        return $this->serverLabel;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
