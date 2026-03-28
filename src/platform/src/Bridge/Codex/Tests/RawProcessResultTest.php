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
use Symfony\AI\Platform\Bridge\Codex\RawProcessResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RawProcessResultTest extends TestCase
{
    public function testGetDataReturnsLastAgentMessage()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'thread.started', 'thread_id' => 'test-123']),
            json_encode(['type' => 'turn.started']),
            json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hello, World!']]),
            json_encode(['type' => 'turn.completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'cached_input_tokens' => 80]]),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $data = $rawResult->getData();

        $this->assertSame('item.completed', $data['type']);
        $this->assertSame('agent_message', $data['item']['type']);
        $this->assertSame('Hello, World!', $data['item']['text']);
        $this->assertSame(100, $data['usage']['input_tokens']);
        $this->assertSame(50, $data['usage']['output_tokens']);
        $this->assertSame(80, $data['usage']['cached_input_tokens']);
    }

    public function testGetDataReturnsLastAgentMessageFromMultiple()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'First message']]),
            json_encode(['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'command' => 'ls']]),
            json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Final answer']]),
            json_encode(['type' => 'turn.completed', 'usage' => ['input_tokens' => 200, 'output_tokens' => 100]]),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $data = $rawResult->getData();

        $this->assertSame('Final answer', $data['item']['text']);
    }

    public function testGetDataReturnsEmptyArrayWhenNoAgentMessage()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'thread.started', 'thread_id' => 'test-123']),
            json_encode(['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 0]]),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->assertSame([], $rawResult->getData());
    }

    public function testGetDataReturnsErrorEventWhenNoAgentMessage()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'thread.started', 'thread_id' => 'test-123']),
            json_encode(['type' => 'error', 'message' => 'Something went wrong']),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $data = $rawResult->getData();

        $this->assertSame('error', $data['type']);
        $this->assertSame('Something went wrong', $data['message']);
    }

    public function testGetDataPrefersAgentMessageOverError()
    {
        $jsonOutput = implode(\PHP_EOL, [
            json_encode(['type' => 'error', 'message' => 'Recoverable error']),
            json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Final answer']]),
            json_encode(['type' => 'turn.completed', 'usage' => ['input_tokens' => 50, 'output_tokens' => 20]]),
        ]);

        $process = new Process(['php', '-r', \sprintf('echo %s;', escapeshellarg($jsonOutput))]);
        $process->start();

        $rawResult = new RawProcessResult($process);
        $data = $rawResult->getData();

        $this->assertSame('item.completed', $data['type']);
        $this->assertSame('Final answer', $data['item']['text']);
    }

    public function testGetDataThrowsOnProcessFailure()
    {
        $process = new Process(['php', '-r', 'fwrite(STDERR, "error"); exit(1);']);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI process failed');

        $rawResult->getData();
    }

    public function testGetDataStreamYieldsJsonLines()
    {
        $lines = [
            json_encode(['type' => 'thread.started', 'thread_id' => 'test-123']),
            json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Hi']]),
            json_encode(['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]]),
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
        $this->assertSame('thread.started', $collected[0]['type']);
        $this->assertSame('item.completed', $collected[1]['type']);
        $this->assertSame('turn.completed', $collected[2]['type']);
    }

    public function testGetDataStreamSkipsEmptyAndInvalidLines()
    {
        $jsonOutput = implode(\PHP_EOL, [
            '',
            'not json',
            json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'done']]),
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
        $this->assertSame('item.completed', $collected[0]['type']);
    }

    public function testGetDataStreamThrowsOnProcessFailure()
    {
        $process = new Process(['php', '-r', 'fwrite(STDERR, "stream error"); exit(1);']);
        $process->start();

        $rawResult = new RawProcessResult($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex CLI process failed');

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
