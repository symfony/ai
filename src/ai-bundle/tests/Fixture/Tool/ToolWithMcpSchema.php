<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Fixture\Tool;

use Mcp\Capability\Attribute\Schema as McpSchema;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\AiBundle\Tests\Fixture\JsonSchema\CategoryProvider;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
#[AsTool('mcp_schema_tool', 'Tool with MCP constraints', method: 'search')]
final class ToolWithMcpSchema
{
    /**
     * @param list<string>         $tags
     * @param array<string, mixed> $options
     */
    public function search(
        #[McpSchema(type: 'string', enum: ['article', 'advertisement', 'obituary'])] ?string $kind = null,
        #[McpSchema(minimum: 0, maximum: 200)] int $limit = 20,
        #[McpSchema(minLength: 0, maxLength: 10, pattern: '^[a-z]*$')] string $term = '',
        #[McpSchema(items: ['type' => 'string'], minItems: 0, maxItems: 5, uniqueItems: false)] array $tags = [],
        #[McpSchema(type: 'object', properties: ['name' => ['type' => 'string']], required: [], additionalProperties: false)] array $options = [],
    ): void {
    }

    public function overridden(
        #[McpSchema(enum: ['first', 'second', 'third'], maxLength: 50)]
        #[Schema(enum: ['second'], maxLength: 10)]
        string $value,
    ): void {
    }

    public function provided(
        #[McpSchema(enum: ['first', 'second', 'third'])]
        #[Schema(provider: CategoryProvider::class)]
        string $value,
    ): void {
    }

    public function defined(
        #[McpSchema(definition: ['type' => 'integer', 'minimum' => 0, 'enum' => [0, 1]])]
        int $value,
    ): void {
    }

    public function exclusiveBounds(
        #[McpSchema(minimum: 0, maximum: 10, exclusiveMinimum: true, exclusiveMaximum: true)] float $exclusive,
        #[McpSchema(minimum: 0, maximum: 10, exclusiveMinimum: false, exclusiveMaximum: false)] float $inclusive,
        #[McpSchema(exclusiveMinimum: true, exclusiveMaximum: true)] float $unbounded,
        #[McpSchema(definition: ['exclusiveMinimum' => 0, 'exclusiveMaximum' => 10])] float $numeric,
    ): void {
    }

    public function platformOnly(#[Schema(enum: ['only'])] string $value): void
    {
    }

    #[McpSchema(definition: ['type' => 'object', 'required' => ['result']])]
    public function methodOnly(string $value): void
    {
    }
}
