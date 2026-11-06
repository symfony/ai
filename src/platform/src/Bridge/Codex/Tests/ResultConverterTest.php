<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\RawProcessResult;
use Symfony\AI\Platform\Bridge\Codex\ResultConverter;
use Symfony\AI\Platform\Bridge\Codex\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\CustomToolCallResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\Process\Process;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsCodex()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Codex('gpt-5-codex')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new Model('sonnet')));
    }

    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello, World!'],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, World!', $result->getContent());
    }

    public function testConvertReportsMcpToolCallsTheCliRanAsMcpCalls()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello, World!'],
            'tool_calls' => [
                ['id' => 'item_0', 'name' => 'read_file', 'arguments' => ['path' => 'README.md'], 'server' => 'files', 'output' => '# Readme', 'error' => null, 'status' => 'completed'],
                ['id' => 'item_1', 'name' => 'read_file', 'arguments' => ['path' => 'missing.md'], 'server' => 'files', 'output' => null, 'error' => 'No such file', 'status' => 'failed'],
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertNull($result->asToolCallResult());
        $this->assertSame('Hello, World!', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(3, $parts);

        $this->assertInstanceOf(McpCallResult::class, $parts[0]);
        $this->assertSame(
            ['files', 'read_file', '{"path":"README.md"}', '# Readme', null, 'item_0', 'completed'],
            [$parts[0]->getServerLabel(), $parts[0]->getName(), $parts[0]->getArguments(), $parts[0]->getContent(), $parts[0]->getError(), $parts[0]->getId(), $parts[0]->getStatus()],
        );

        $this->assertInstanceOf(McpCallResult::class, $parts[1]);
        $this->assertSame(
            ['files', 'read_file', '{"path":"missing.md"}', null, 'No such file', 'item_1', 'failed'],
            [$parts[1]->getServerLabel(), $parts[1]->getName(), $parts[1]->getArguments(), $parts[1]->getContent(), $parts[1]->getError(), $parts[1]->getId(), $parts[1]->getStatus()],
        );

        $this->assertInstanceOf(TextResult::class, $parts[2]);
        $this->assertSame('Hello, World!', $parts[2]->getContent());
    }

    public function testConvertReportsToolCallsWithoutServerTheCliRanAsCustomToolCalls()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message', 'text' => 'Hello, World!'],
            'tool_calls' => [
                ['id' => 'call-1', 'name' => 'symfony_logs', 'arguments' => [], 'server' => null, 'output' => null, 'error' => null, 'status' => 'completed'],
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertNull($result->asToolCallResult());
        $this->assertSame('Hello, World!', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(CustomToolCallResult::class, $parts[0]);
        $this->assertSame(['call-1', 'symfony_logs', '{}', 'completed'], [$parts[0]->getId(), $parts[0]->getName(), $parts[0]->getInput(), $parts[0]->getStatus()]);
    }

    public function testConvertThrowsOnEmptyData()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI did not return any result.');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnErrorResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'error',
            'message' => 'Something went wrong',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI error: "Something went wrong"');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnMissingTextField()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'item.completed',
            'item' => ['type' => 'agent_message'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI result does not contain a text field.');

        $converter->convert($rawResult);
    }

    public function testConvertStreamingReturnsStreamResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TokenUsage::class, $chunks[1]);
        $this->assertSame(10, $chunks[1]->getPromptTokens());
        $this->assertSame(5, $chunks[1]->getCompletionTokens());
    }

    public function testConvertStreamingYieldsMultipleAgentMessages()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'First part.']],
                ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'command' => 'ls']],
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Second part.']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 50, 'output_tokens' => 20]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('First part.', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('Second part.', $chunks[1]->getText());
        $this->assertInstanceOf(TokenUsage::class, $chunks[2]);
        $this->assertSame(50, $chunks[2]->getPromptTokens());
        $this->assertSame(20, $chunks[2]->getCompletionTokens());
    }

    public function testConvertStreamingIgnoresNonAgentEvents()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'thread.started', 'thread_id' => 'test-123'],
                ['type' => 'turn.started'],
                ['type' => 'item.started', 'item' => ['type' => 'command_execution', 'status' => 'in_progress']],
                ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'command' => 'ls']],
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello']],
                ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TokenUsage::class, $chunks[1]);
    }

    public function testConvertStreamingThrowsWhenTurnCompletedIsMissing()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'thread.started', 'thread_id' => 'test-123'],
                ['type' => 'turn.started'],
                ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Partial answer']],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Codex stream ended before turn.completed.');

        iterator_to_array($result->getContent(), false);
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertInstanceOf(TokenUsageExtractor::class, $converter->getTokenUsageExtractor());
    }

    /**
     * Recorded from Codex CLI 0.166.0 calling the read_file tool of an MCP server.
     */
    public function testConvertRecordedRunReportsTheMcpCallAsResolved()
    {
        $process = new Process(['php', '-r', 'readfile($argv[1]);', __DIR__.'/Fixtures/mcp_tool_call.jsonl']);
        $process->start();

        $result = (new ResultConverter())->convert(new RawProcessResult($process));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertNull($result->asToolCallResult());
        $this->assertSame('The project codename is Lantern.', $result->asText());

        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(McpCallResult::class, $parts[0]);
        $this->assertSame(
            ['files', 'read_file', '{"path":"README.md"}', "# Lantern\n\nThe project codename is Lantern. It has one rule: keep answers short.\n", null, 'item_1', 'completed'],
            [$parts[0]->getServerLabel(), $parts[0]->getName(), $parts[0]->getArguments(), $parts[0]->getContent(), $parts[0]->getError(), $parts[0]->getId(), $parts[0]->getStatus()],
        );
        $this->assertInstanceOf(TextResult::class, $parts[1]);
    }
}
