<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\McpToolNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ApprovalFilter;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\McpTool;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class McpToolNormalizerTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(array $expected, McpTool $mcpTool)
    {
        $actual = (new McpToolNormalizer())->normalize($mcpTool, null, [Contract::CONTEXT_MODEL => new Gpt('gpt-4o-mini')]);
        $this->assertSame($expected, $actual);
    }

    public static function normalizeProvider(): \Generator
    {
        yield 'minimal' => [
            [
                'type' => 'mcp',
                'server_label' => 'dmcp',
                'server_url' => 'https://dmcp-server.deno.dev/sse',
                'require_approval' => 'never',
            ],
            new McpTool(
                serverLabel: 'dmcp',
                serverUrl: 'https://dmcp-server.deno.dev/sse',
            ),
        ];

        yield 'with all options' => [
            [
                'type' => 'mcp',
                'server_label' => 'my-server',
                'server_url' => 'https://example.com/mcp',
                'require_approval' => 'always',
                'server_description' => 'A useful MCP server',
                'headers' => ['Authorization' => 'Bearer token123'],
                'allowed_tools' => ['tool_a', 'tool_b'],
            ],
            new McpTool(
                serverLabel: 'my-server',
                serverUrl: 'https://example.com/mcp',
                serverDescription: 'A useful MCP server',
                headers: ['Authorization' => 'Bearer token123'],
                requireApproval: 'always',
                allowedTools: ['tool_a', 'tool_b'],
            ),
        ];

        yield 'with approval filter' => [
            [
                'type' => 'mcp',
                'server_label' => 'dmcp',
                'server_url' => 'https://dmcp-server.deno.dev/sse',
                'require_approval' => [
                    'never' => [
                        'tool_names' => ['safe_tool'],
                    ],
                ],
            ],
            new McpTool(
                serverLabel: 'dmcp',
                serverUrl: 'https://dmcp-server.deno.dev/sse',
                requireApproval: new ApprovalFilter(['safe_tool']),
            ),
        ];
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new McpToolNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model]),
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $mcpTool = new McpTool(serverLabel: 'test', serverUrl: 'https://example.com');
        $gpt = new Gpt('gpt-4o-mini');

        yield 'supported' => [$mcpTool, $gpt, true];
        yield 'unsupported model' => [$mcpTool, new Model('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
