<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\AiBundle\Profiler\DataCollector as AiDataCollector;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\AiCollectorFormatter;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AiCollectorFormatterTest extends TestCase
{
    private AiCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new AiCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('ai', $this->formatter->getName());
    }

    public function testGetSummary()
    {
        $collector = $this->createAiCollector([
            'platform_calls' => [[]],
            'tools' => [[], []],
            'tool_calls' => [[]],
            'messages' => [[]],
            'chats' => [[]],
            'agents' => [[]],
            'stores' => [[], []],
        ]);

        $summary = $this->formatter->getSummary($collector);

        $this->assertSame([
            'platform_call_count' => 1,
            'tool_count' => 2,
            'tool_call_count' => 1,
            'message_count' => 1,
            'chat_count' => 1,
            'agent_call_count' => 1,
            'store_call_count' => 2,
        ], $summary);
    }

    public function testFormatNormalizesAiCollectorData()
    {
        $collector = $this->createAiCollector([
            'platform_calls' => [[
                'model' => 'gpt-4.1',
                'input' => $this->createMessageBag(),
                'options' => [
                    'stream' => false,
                    'tools' => [$this->createTool()],
                ],
                'result_type' => 'tool_calls',
                'result' => [new ToolCall('call-2', 'search_docs', ['query' => 'formatter'])],
                'metadata' => new Metadata(['request_id' => 'req-123']),
            ]],
            'tools' => [$this->createTool()],
            'tool_calls' => [$this->createToolResult()],
            'messages' => [[
                'bag' => $this->createMessageBag(),
                'saved_at' => new \DateTimeImmutable('2026-04-17T10:00:00+00:00'),
            ]],
            'chats' => [[
                'action' => 'submit',
                'message' => new UserMessage(new Text('Tell me more')),
                'submitted_at' => new \DateTimeImmutable('2026-04-17T10:01:00+00:00'),
            ]],
            'agents' => [[
                'messages' => $this->createMessageBag(),
                'options' => ['temperature' => 0.2],
                'called_at' => new \DateTimeImmutable('2026-04-17T10:02:00+00:00'),
            ]],
            'stores' => [[
                'method' => 'query',
                'query' => new TextQuery(['formatters', 'collectors']),
                'options' => ['limit' => 5],
                'called_at' => new \DateTimeImmutable('2026-04-17T10:03:00+00:00'),
            ]],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['platform_call_count']);
        $this->assertSame(1, $result['tool_count']);
        $this->assertSame(1, $result['tool_call_count']);
        $this->assertSame(1, $result['message_count']);
        $this->assertSame(1, $result['chat_count']);
        $this->assertSame(1, $result['agent_call_count']);
        $this->assertSame(1, $result['store_call_count']);

        $this->assertSame('gpt-4.1', $result['platform_calls'][0]['model']);
        $this->assertSame('tool_calls', $result['platform_calls'][0]['result_type']);
        $this->assertSame('user', $result['platform_calls'][0]['input']['messages'][0]['role']);
        $this->assertSame('assistant', $result['platform_calls'][0]['input']['messages'][1]['role']);
        $this->assertSame('search_docs', $result['platform_calls'][0]['options']['tools'][0]['name']);
        $this->assertSame('req-123', $result['platform_calls'][0]['metadata']['request_id']);

        $this->assertSame('search_docs', $result['tools'][0]['name']);
        $this->assertSame('App\\Tool\\SearchTool', $result['tools'][0]['reference']['class']);

        $this->assertSame('search_docs', $result['tool_calls'][0]['tool_call']['name']);
        $this->assertNull($result['tool_calls'][0]['sources']);

        $this->assertSame('2026-04-17T10:00:00+00:00', $result['messages'][0]['saved_at']);
        $this->assertSame(2, $result['messages'][0]['bag']['message_count']);

        $this->assertSame('submit', $result['chats'][0]['action']);
        $this->assertSame('Tell me more', $result['chats'][0]['message']['content'][0]['text']);

        $this->assertSame('2026-04-17T10:02:00+00:00', $result['agents'][0]['called_at']);
        $this->assertSame(0.2, $result['agents'][0]['options']['temperature']);

        $this->assertSame('query', $result['stores'][0]['method']);
        $this->assertSame('text', $result['stores'][0]['query']['type']);
        $this->assertSame(['formatters', 'collectors'], $result['stores'][0]['query']['texts']);
    }

    public function testFormatFallsBackToObjectSummaryForUnknownObjects()
    {
        $collector = $this->createAiCollector([
            'platform_calls' => [[
                'model' => 'gpt-4.1',
                'input' => new class {
                },
                'options' => [],
                'result_type' => 'text',
                'result' => new class {
                },
                'metadata' => new Metadata(),
            ]],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('object', $result['platform_calls'][0]['input']['type']);
        $this->assertSame('object', $result['platform_calls'][0]['result']['type']);
    }

    public function testFormatReturnsEmptySectionsWhenCollectorDataIsMissing()
    {
        $collector = $this->createAiCollector([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['platform_call_count']);
        $this->assertSame([], $result['platform_calls']);
        $this->assertSame([], $result['tools']);
        $this->assertSame([], $result['tool_calls']);
        $this->assertSame([], $result['messages']);
        $this->assertSame([], $result['chats']);
        $this->assertSame([], $result['agents']);
        $this->assertSame([], $result['stores']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createAiCollector(array $data): AiDataCollector
    {
        $collector = (new \ReflectionClass(AiDataCollector::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DataCollector::class, 'data'))->setValue($collector, $data);

        return $collector;
    }

    private function createMessageBag(): MessageBag
    {
        return new MessageBag(
            new UserMessage(new Text('Find formatter coverage')),
            new AssistantMessage('Calling a tool', [new ToolCall('call-1', 'search_docs', ['query' => 'formatter'])]),
        );
    }

    private function createTool(): Tool
    {
        return new Tool(
            new ExecutionReference('App\\Tool\\SearchTool'),
            'search_docs',
            'Search project documentation',
            [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query'],
                ],
                'required' => ['query'],
                'additionalProperties' => false,
            ],
        );
    }

    private function createToolResult(): ToolResult
    {
        return new ToolResult(
            new ToolCall('call-3', 'search_docs', ['query' => 'bridge']),
            ['matches' => 4],
        );
    }
}
