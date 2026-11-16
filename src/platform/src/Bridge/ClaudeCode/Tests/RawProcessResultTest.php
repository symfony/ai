<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ClaudeCode\RawProcessResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RawProcessResultTest extends TestCase
{
    public function testGetDataReturnsResultMessage()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'Hello']]]]),
            json_encode(['type' => 'result', 'result' => 'Hello, World!', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]]),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $data = $rawResult->getData();

        $this->assertSame('result', $data['type']);
        $this->assertSame('Hello, World!', $data['result']);
        $this->assertSame(10, $data['usage']['input_tokens']);
        $this->assertSame(5, $data['usage']['output_tokens']);
    }

    public function testGetDataCollectsToolCalls()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'assistant', 'message' => ['content' => [
                ['type' => 'tool_use', 'id' => 'toolu_123', 'name' => 'symfony_logs', 'input' => ['channel' => 'app']],
            ]]]),
            json_encode(['type' => 'result', 'result' => 'Hello, World!', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]]),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $data = $rawResult->getData();

        $this->assertCount(1, $data['tool_calls']);
        $this->assertSame('toolu_123', $data['tool_calls'][0]['id']);
        $this->assertSame('symfony_logs', $data['tool_calls'][0]['name']);
        $this->assertSame(['channel' => 'app'], $data['tool_calls'][0]['arguments']);
    }

    public function testGetDataPairsToolCallsWithTheirToolResults()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'assistant', 'message' => ['content' => [
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'Read', 'input' => ['file_path' => 'README.md']],
                ['type' => 'tool_use', 'id' => 'toolu_2', 'name' => 'Bash', 'input' => ['command' => 'false']],
                ['type' => 'tool_use', 'id' => 'toolu_3', 'name' => 'Glob', 'input' => []],
            ]]]),
            json_encode(['type' => 'user', 'message' => ['content' => [
                ['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => [['type' => 'text', 'text' => '# Readme']]],
                ['type' => 'tool_result', 'tool_use_id' => 'toolu_2', 'content' => 'Exit code 1', 'is_error' => true],
            ]]]),
            json_encode(['type' => 'result', 'result' => 'Hello, World!']),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $data = (new RawProcessResult($process))->getData();

        $this->assertSame(
            [['toolu_1', '# Readme', false], ['toolu_2', 'Exit code 1', true], ['toolu_3', null, null]],
            array_map(static fn (array $toolCall): array => [$toolCall['id'], $toolCall['output'], $toolCall['is_error']], $data['tool_calls']),
        );
    }

    public function testGetDataDeduplicatesToolCallsAcrossStreamAndAssistantEvents()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_123', 'name' => 'symfony_logs', 'input' => ['channel' => 'app']]]]),
            json_encode(['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]]),
            json_encode(['type' => 'assistant', 'message' => ['content' => [
                ['type' => 'tool_use', 'id' => 'toolu_123', 'name' => 'symfony_logs', 'input' => ['channel' => 'app']],
            ]]]),
            json_encode(['type' => 'result', 'result' => 'Hello, World!', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]]),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $data = $rawResult->getData();

        $this->assertCount(1, $data['tool_calls']);
        $this->assertSame('toolu_123', $data['tool_calls'][0]['id']);
        $this->assertSame('symfony_logs', $data['tool_calls'][0]['name']);
    }

    public function testGetDataReturnsEmptyArrayWhenNoResultMessage()
    {
        $jsonOutput = json_encode(['type' => 'assistant', 'message' => ['content' => []]]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->assertSame([], $rawResult->getData());
    }

    public function testGetDataThrowsOnProcessFailure()
    {
        $process = new Process(['php', '-r', 'fwrite(STDERR, "error"); exit(1);']);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI process failed');

        $rawResult->getData();
    }

    public function testGetDataFailureReportsCliErrorInsteadOfUnrelatedStderr()
    {
        $jsonOutput = json_encode([
            'type' => 'result',
            'subtype' => 'error_max_turns',
            'is_error' => true,
            'errors' => ['Reached maximum number of turns (1)'],
        ]);

        $phpCode = \sprintf('fwrite(STDERR, "warning: connectors are disabled"); echo %s; exit(1);', escapeshellarg($jsonOutput));
        $process = new Process(['php', '-r', $phpCode]);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI process failed: "Reached maximum number of turns (1)"');

        $rawResult->getData();
    }

    public function testGetDataFailureFallsBackToResultSubtype()
    {
        $jsonOutput = json_encode(['type' => 'result', 'subtype' => 'error_during_execution', 'is_error' => true]);

        $phpCode = \sprintf('echo %s; exit(1);', escapeshellarg($jsonOutput));
        $process = new Process(['php', '-r', $phpCode]);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI process failed: "error_during_execution"');

        $rawResult->getData();
    }

    public function testGetDataFailureFallsBackToExitCodeWithoutAnyOutput()
    {
        $process = new Process(['php', '-r', 'exit(3);']);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI process failed: "exit code 3"');

        $rawResult->getData();
    }

    public function testGetDataStreamFailureReportsCliError()
    {
        $jsonOutput = json_encode([
            'type' => 'result',
            'subtype' => 'error_max_turns',
            'is_error' => true,
            'errors' => ['Reached maximum number of turns (2)'],
        ]);

        $phpCode = \sprintf('fwrite(STDERR, "warning: connectors are disabled"); echo %s.PHP_EOL; exit(1);', escapeshellarg($jsonOutput));
        $process = new Process(['php', '-r', $phpCode]);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI process failed: "Reached maximum number of turns (2)"');

        foreach ($rawResult->getDataStream() as $data) {
        }
    }

    public function testGetDataStreamYieldsJsonLines()
    {
        $lines = [
            json_encode(['type' => 'system', 'data' => 'init']),
            json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'Hi']]]]),
            json_encode(['type' => 'result', 'result' => 'Hi']),
        ];

        $phpCode = 'foreach ('.var_export($lines, true).' as $line) { echo $line.PHP_EOL; usleep(1000); }';
        $process = new Process(['php', '-r', $phpCode]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $collected = [];

        foreach ($rawResult->getDataStream() as $data) {
            $collected[] = $data;
        }

        $this->assertCount(3, $collected);
        $this->assertSame('system', $collected[0]['type']);
        $this->assertSame('assistant', $collected[1]['type']);
        $this->assertSame('result', $collected[2]['type']);
    }

    public function testGetDataStreamSkipsEmptyAndInvalidLines()
    {
        $jsonOutput = implode(\PHP_EOL, [
            '',
            'not json',
            json_encode(['type' => 'result', 'result' => 'done']),
            '',
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $collected = [];

        foreach ($rawResult->getDataStream() as $data) {
            $collected[] = $data;
        }

        $this->assertCount(1, $collected);
        $this->assertSame('result', $collected[0]['type']);
    }

    public function testGetDataStreamThrowsOnProcessFailure()
    {
        $process = new Process(['php', '-r', 'fwrite(STDERR, "stream error"); exit(1);']);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI process failed');

        foreach ($rawResult->getDataStream() as $data) {
        }
    }

    public function testGetObjectReturnsProcess()
    {
        $process = new Process(['php', '-r', 'echo "test";']);

        $rawResult = new RawProcessResult($process);

        $this->assertSame($process, $rawResult->getObject());
    }

    /**
     * Recorded from Claude Code 2.1.316: it writes the assistant message with the call before the block stops.
     */
    public function testGetDataReportsEachRecordedToolCallOnce()
    {
        $process = new Process(['php', '-r', 'readfile($argv[1]);', __DIR__.'/Fixtures/read_tool.jsonl']);
        $process->start();

        $data = (new RawProcessResult($process))->getData();

        $this->assertSame(['toolu_01'], array_column($data['tool_calls'], 'id'));
    }

    public function testGetDataNamesTheConfiguredServerOfEachRecordedMcpToolCall()
    {
        $process = new Process(['php', '-r', 'readfile($argv[1]);', __DIR__.'/Fixtures/mcp_tool_server_names.jsonl']);
        $process->start();

        $data = (new RawProcessResult($process))->getData();

        $this->assertSame(
            [
                ['ToolSearch', null, null],
                ['mcp__file__store__read_file', 'file__store', 'read_file'],
                ['mcp__my_files_v2__read_file', 'my files.v2', 'read_file'],
                ['mcp__caf___read_file', 'café', 'read_file'],
                ['mcp__srv__x__read_file', 'srv😀x', 'read_file'],
                ['mcp__claude_ai_My_Drive__read_file', 'claude.ai My  Drive!', 'read_file'],
            ],
            array_map(static fn (array $toolCall): array => [$toolCall['name'], $toolCall['server'] ?? null, $toolCall['tool'] ?? null], $data['tool_calls']),
        );
    }

    public function testGetDataMatchesTheLongestServerNameOfAnMcpToolCall()
    {
        $lines = [
            ['type' => 'system', 'subtype' => 'init', 'mcp_servers' => [['name' => 'a', 'status' => 'connected'], ['name' => 'a__b', 'status' => 'connected']]],
            ['type' => 'assistant', 'message' => ['content' => [['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'mcp__a__b__read', 'input' => []]]]],
            ['type' => 'result', 'result' => 'Done.'],
        ];
        $process = new Process(['php', '-r', 'echo $argv[1];', implode(\PHP_EOL, array_map(static fn (array $line): string => json_encode($line, \JSON_THROW_ON_ERROR), $lines))]);
        $process->start();

        $data = (new RawProcessResult($process))->getData();

        $this->assertSame(['a__b', 'read'], [$data['tool_calls'][0]['server'] ?? null, $data['tool_calls'][0]['tool'] ?? null]);
    }

    public function testGetDataLeavesTheServerOfAnMcpToolCallOfAnUnlistedServerOpen()
    {
        $lines = [
            ['type' => 'system', 'subtype' => 'init', 'mcp_servers' => [['name' => 'files', 'status' => 'connected']]],
            ['type' => 'assistant', 'message' => ['content' => [['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'mcp__other__read', 'input' => []]]]],
            ['type' => 'result', 'result' => 'Done.'],
        ];
        $process = new Process(['php', '-r', 'echo $argv[1];', implode(\PHP_EOL, array_map(static fn (array $line): string => json_encode($line, \JSON_THROW_ON_ERROR), $lines))]);
        $process->start();

        $data = (new RawProcessResult($process))->getData();

        $this->assertSame([null, null], [$data['tool_calls'][0]['server'] ?? null, $data['tool_calls'][0]['tool'] ?? null]);
    }
}
