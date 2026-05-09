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
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Bridge\ClaudeCode\ResultConverter;
use Symfony\AI\Platform\Bridge\ClaudeCode\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsClaudeCode()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new ClaudeCode('sonnet')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new Claude('claude-3-5-sonnet-latest')));
    }

    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'result' => 'Hello, World!',
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, World!', $result->getContent());
    }

    public function testConvertThrowsOnEmptyData()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI did not return any result.');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnErrorResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
            'is_error' => true,
            'result' => 'Something went wrong',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI error: "Something went wrong"');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnMissingResultField()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([
            'type' => 'result',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Claude Code CLI result does not contain a "result" field.');

        $converter->convert($rawResult);
    }

    public function testConvertStreamingReturnsStreamResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]],
                ['type' => 'result', 'result' => 'Hello'],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
    }

    public function testConvertStreamingYieldsTextDeltas()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello, ']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'World!']]],
                ['type' => 'result', 'result' => 'Hello, World!'],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello, ', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('World!', $chunks[1]->getText());
    }

    public function testConvertStreamingIgnoresNonTextEvents()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'system', 'subtype' => 'init'],
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]],
                ['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'Hello']]]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop']],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => 'Hello'],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
    }

    public function testConvertStreamingYieldsToolCallDeltas()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'message_start']],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01ABC123', 'name' => 'get_weather']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"loc']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'ation": "']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'Berlin"}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => ''],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(5, $chunks);

        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('toolu_01ABC123', $chunks[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getName());

        $this->assertInstanceOf(ToolInputDelta::class, $chunks[1]);
        $this->assertSame('toolu_01ABC123', $chunks[1]->getId());
        $this->assertSame('{"loc', $chunks[1]->getPartialJson());
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[2]);
        $this->assertSame('ation": "', $chunks[2]->getPartialJson());
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[3]);
        $this->assertSame('Berlin"}', $chunks[3]->getPartialJson());

        $this->assertInstanceOf(ToolCallComplete::class, $chunks[4]);
        $toolCalls = $chunks[4]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01ABC123', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testConvertStreamingYieldsToolCallWithEmptyInput()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_empty', 'name' => 'list_files']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => ''],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);

        $this->assertInstanceOf(ToolCallComplete::class, $chunks[1]);
        $toolCalls = $chunks[1]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_empty', $toolCalls[0]->getId());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testConvertStreamingInterleavesTextAndToolCallDeltas()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Let me check.']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01XYZ789', 'name' => 'get_weather']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"location":"Paris"}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 1]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => 'Let me check.'],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(4, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Let me check.', $chunks[0]->getText());
        $this->assertInstanceOf(ToolCallStart::class, $chunks[1]);
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[2]);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[3]);
        $this->assertSame(['location' => 'Paris'], $chunks[3]->getToolCalls()[0]->getArguments());
    }

    public function testConvertStreamingYieldsMultipleToolCallsInOrder()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_a', 'name' => 'first']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"x":1}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 0]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_b', 'name' => 'second']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"y":2}']]],
                ['type' => 'stream_event', 'event' => ['type' => 'content_block_stop', 'index' => 1]],
                ['type' => 'stream_event', 'event' => ['type' => 'message_stop']],
                ['type' => 'result', 'result' => ''],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $toolCallComplete = $chunks[\count($chunks) - 1];
        $this->assertInstanceOf(ToolCallComplete::class, $toolCallComplete);
        $toolCalls = $toolCallComplete->getToolCalls();
        $this->assertCount(2, $toolCalls);
        $this->assertSame('toolu_a', $toolCalls[0]->getId());
        $this->assertSame(['x' => 1], $toolCalls[0]->getArguments());
        $this->assertSame('toolu_b', $toolCalls[1]->getId());
        $this->assertSame(['y' => 2], $toolCalls[1]->getArguments());
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertInstanceOf(TokenUsageExtractor::class, $converter->getTokenUsageExtractor());
    }
}
