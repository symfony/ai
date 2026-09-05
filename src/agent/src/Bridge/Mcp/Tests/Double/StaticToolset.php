<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp\Tests\Double;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool as McpTool;
use Symfony\AI\Agent\Bridge\Mcp\ToolsetInterface;

/**
 * An already-connected toolset answering from canned results.
 */
final class StaticToolset implements ToolsetInterface
{
    /**
     * @var list<array{name: string, arguments: array<string, mixed>}>
     */
    public array $calls = [];

    /**
     * @param list<McpTool>                            $tools
     * @param array<string, CallToolResult|\Throwable> $results
     */
    public function __construct(
        private readonly string $name = 'demo',
        private readonly array $tools = [],
        private readonly array $results = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function callTool(string $name, array $arguments = []): CallToolResult
    {
        $this->calls[] = ['name' => $name, 'arguments' => $arguments];

        $result = $this->results[$name] ?? new CallToolResult([]);

        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }
}
