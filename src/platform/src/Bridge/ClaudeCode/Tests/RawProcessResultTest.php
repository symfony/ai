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
}
