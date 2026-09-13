<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Result\ToolCall;

final class AssistantMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new AssistantMessage(new Text('Hello')), context: [
            Contract::CONTEXT_MODEL => new Claude('claude-3-5-sonnet-latest'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not an assistant message'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertSame([AssistantMessage::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalizeReplaysBothBlocksOfAWebSearch()
    {
        $call = ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => 'Symfony AI']];
        $result = ['type' => 'web_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => []];

        $normalized = (new AssistantMessageNormalizer())->normalize(new AssistantMessage(
            new WebSearch('Symfony AI', 'srvtoolu_1', 'completed', ['Symfony AI'], json_encode([$call, $result])),
            new Text('Symfony AI integrates AI capabilities.'),
        ));

        // One search, but Anthropic rejects either block without the other, so both are re-sent.
        $this->assertSame([
            'role' => 'assistant',
            'content' => [$call, $result, ['type' => 'text', 'text' => 'Symfony AI integrates AI capabilities.']],
        ], $normalized);
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function unreplayableWebSearchProvider(): iterable
    {
        yield 'no signature at all' => [null];
        yield 'a signature that is not JSON' => ['not json'];
        yield 'a signature that is not a list of blocks' => ['"a string"'];
        yield 'a block type Anthropic does not take back' => ['[{"type":"web_fetch_tool_result"}]'];
        yield 'an item belonging to another provider' => ['{"type":"web_search_call","id":"ws_1","status":"completed"}'];
    }

    #[DataProvider('unreplayableWebSearchProvider')]
    public function testNormalizeDropsAWebSearchItCannotReplay(?string $signature)
    {
        $normalized = (new AssistantMessageNormalizer())->normalize(new AssistantMessage(
            new WebSearch('Symfony AI', 'ws_1', 'completed', ['Symfony AI'], $signature),
            new Text('Symfony AI integrates AI capabilities.'),
        ));

        // Dropping the search keeps the turn replayable; sending Claude a block it did not
        // produce would fail the request instead.
        $this->assertSame([
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'Symfony AI integrates AI capabilities.']],
        ], $normalized);
    }

    public function testNormalizeRejectsAWebSearchInitiatedByCodeExecution()
    {
        $message = new AssistantMessage(new WebSearch('Symfony AI', 'srvtoolu_1', 'completed', ['Symfony AI'], json_encode([[
            'type' => 'server_tool_use',
            'id' => 'srvtoolu_1',
            'name' => 'web_search',
            'input' => ['query' => 'Symfony AI'],
            'caller' => ['type' => 'code_execution_20260120', 'tool_id' => 'srvtoolu_code_1'],
        ]])));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('surrounding code execution blocks are not supported');

        (new AssistantMessageNormalizer())->normalize($message);
    }

    /**
     * @param array{role: 'assistant', content: string|list<array{type: 'tool_use', id: string, name: string, input: array<string, mixed>|\stdClass}>} $expectedOutput
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(AssistantMessage $message, array $expectedOutput)
    {
        $normalizer = new AssistantMessageNormalizer();

        $normalized = $normalizer->normalize($message);

        $this->assertEquals($expectedOutput, $normalized);
    }

    /**
     * @return iterable<string, array{
     *     0: AssistantMessage,
     *     1: array{
     *         role: 'assistant',
     *         content: string|list<array{
     *             type: 'tool_use'|'text'|'thinking',
     *             id?: string,
     *             name?: string,
     *             input?: array<string, mixed>|\stdClass,
     *             text?: string,
     *             thinking?: string,
     *             signature?: string
     *         }>
     *     }
     * }>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'assistant message' => [
            new AssistantMessage(new Text('Great to meet you. What would you like to know?')),
            [
                'role' => 'assistant',
                'content' => 'Great to meet you. What would you like to know?',
            ],
        ];
        yield 'function call' => [
            new AssistantMessage(new ToolCall('id1', 'name1', ['arg1' => '123'])),
            [
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'id1',
                        'name' => 'name1',
                        'input' => ['arg1' => '123'],
                    ],
                ],
            ],
        ];
        yield 'function call without parameters' => [
            new AssistantMessage(new ToolCall('id1', 'name1')),
            [
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'id1',
                        'name' => 'name1',
                        'input' => new \stdClass(),
                    ],
                ],
            ],
        ];

        yield 'text prefix with single tool call' => [
            new AssistantMessage(
                new Text('I\'ll look that up for you.'),
                new ToolCall('id1', 'search', ['query' => 'symfony']),
            ),
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => "I'll look that up for you."],
                    ['type' => 'tool_use', 'id' => 'id1', 'name' => 'search', 'input' => ['query' => 'symfony']],
                ],
            ],
        ];

        yield 'text prefix with multiple tool calls' => [
            new AssistantMessage(
                new Text('Let me run both tools.'),
                new ToolCall('id1', 'read', ['path' => '/etc/hosts']),
                new ToolCall('id2', 'write', ['path' => '/tmp/out', 'content' => 'ok']),
            ),
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Let me run both tools.'],
                    ['type' => 'tool_use', 'id' => 'id1', 'name' => 'read',  'input' => ['path' => '/etc/hosts']],
                    ['type' => 'tool_use', 'id' => 'id2', 'name' => 'write', 'input' => ['path' => '/tmp/out', 'content' => 'ok']],
                ],
            ],
        ];

        yield 'text prefix with no-argument tool call' => [
            new AssistantMessage(
                new Text('Checking the current date.'),
                new ToolCall('id1', 'get_date'),
            ),
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Checking the current date.'],
                    ['type' => 'tool_use', 'id' => 'id1', 'name' => 'get_date', 'input' => new \stdClass()],
                ],
            ],
        ];

        yield 'thinking with text' => [
            new AssistantMessage(
                new Thinking('Let me reason about this...', 'sig_abc123'),
                new Text('The answer is 42.'),
            ),
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'thinking', 'thinking' => 'Let me reason about this...', 'signature' => 'sig_abc123'],
                    ['type' => 'text', 'text' => 'The answer is 42.'],
                ],
            ],
        ];

        yield 'thinking with text and tool calls' => [
            new AssistantMessage(
                new Thinking('I need to look this up.', 'sig_xyz'),
                new Text('Let me search.'),
                new ToolCall('id1', 'search', ['query' => 'symfony']),
            ),
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'thinking', 'thinking' => 'I need to look this up.', 'signature' => 'sig_xyz'],
                    ['type' => 'text', 'text' => 'Let me search.'],
                    ['type' => 'tool_use', 'id' => 'id1', 'name' => 'search', 'input' => ['query' => 'symfony']],
                ],
            ],
        ];

        yield 'thinking without signature' => [
            new AssistantMessage(
                new Thinking('Quick thought.'),
                new Text('Done.'),
            ),
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'thinking', 'thinking' => 'Quick thought.'],
                    ['type' => 'text', 'text' => 'Done.'],
                ],
            ],
        ];

        yield 'thinking with tool calls but no text' => [
            new AssistantMessage(
                new Thinking('I should read this file.', 'sig_123'),
                new ToolCall('id1', 'read', ['path' => '/etc/hosts']),
            ),
            [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'thinking', 'thinking' => 'I should read this file.', 'signature' => 'sig_123'],
                    ['type' => 'tool_use', 'id' => 'id1', 'name' => 'read', 'input' => ['path' => '/etc/hosts']],
                ],
            ],
        ];
    }
}
