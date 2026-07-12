<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Acp\Acp;
use Symfony\AI\Platform\Bridge\Acp\ResultConverter;
use Symfony\AI\Platform\Bridge\Acp\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @covers \Symfony\AI\Platform\Bridge\Acp\ResultConverter
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsAcpModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Acp('acp-v1')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new \Symfony\AI\Platform\Model('other')));
    }

    public function testConvertStreamingTextDelta()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['jsonrpc' => '2.0', 'method' => 'agent/messageStream', 'params' => ['content' => ['type' => 'text', 'text' => 'Hello']]],
                ['jsonrpc' => '2.0', 'method' => 'agent/messageStream', 'params' => ['content' => ['type' => 'text', 'text' => ', World!']]],
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
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame(', World!', $chunks[1]->getText());
    }

    public function testConvertStreamingThinkingDelta()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['jsonrpc' => '2.0', 'method' => 'agent/messageStream', 'params' => ['content' => ['type' => 'thought', 'text' => 'Let me think...']]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[0]);
        $this->assertSame('Let me think...', $chunks[0]->getThinking());
    }

    public function testConvertStreamingToolCallStart()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'tool_call', 'toolCallId' => 'call-1', 'title' => 'read_file']]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('call-1', $chunks[0]->getId());
        $this->assertSame('read_file', $chunks[0]->getName());
    }

    public function testConvertStreamingToolInputDelta()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'tool_call_update', 'toolCallId' => 'call-1', 'title' => 'read_file', 'status' => 'in_progress', 'rawInput' => ['path' => '/tmp/test.txt']]]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[0]);
        $this->assertSame('call-1', $chunks[0]->getId());
        $this->assertSame('read_file', $chunks[0]->getName());
        $this->assertJson($chunks[0]->getPartialJson());
    }

    public function testConvertStreamingToolCallComplete()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'tool_call_update', 'toolCallId' => 'call-1', 'title' => 'read_file', 'status' => 'completed', 'rawOutput' => ['output' => ['content' => 'file content']]]]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call-1', $toolCalls[0]->getId());
        $this->assertSame('read_file', $toolCalls[0]->getName());
        $this->assertSame(['content' => 'file content'], $toolCalls[0]->getArguments());
    }

    public function testConvertStreamingToolCallCompleteWithScalarOutput()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'tool_call_update', 'toolCallId' => 'call-1', 'title' => 'read_file', 'status' => 'completed', 'rawOutput' => ['output' => 'file content']]]],
            ],
        );

        $result = $converter->convert($rawResult, ['stream' => true]);

        $chunks = [];
        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame(['output' => 'file content'], $toolCalls[0]->getArguments());
    }

    public function testConvertStreamingAgentMessageChunk()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            [],
            [
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'agent_message_chunk', 'content' => ['type' => 'text', 'text' => 'Hello']]]],
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'agent_message_chunk', 'content' => ['type' => 'thought', 'text' => 'thinking']]]],
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
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[1]);
        $this->assertSame('thinking', $chunks[1]->getThinking());
    }

    public function testConvertNonStreamingTextResult()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            ['done' => true],
            [
                ['jsonrpc' => '2.0', 'method' => 'agent/messageStream', 'params' => ['content' => ['type' => 'text', 'text' => 'Hello, World!']]],
            ],
        );

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, World!', $result->getContent());
    }

    public function testConvertNonStreamingWithToolCalls()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            ['done' => true],
            [
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'tool_call', 'toolCallId' => 'call-1', 'title' => 'read_file']]],
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'tool_call_update', 'toolCallId' => 'call-1', 'title' => 'read_file', 'status' => 'in_progress', 'rawInput' => ['path' => '/tmp/test.txt']]]],
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'tool_call_update', 'toolCallId' => 'call-1', 'title' => 'read_file', 'status' => 'completed', 'rawOutput' => ['output' => ['content' => 'file content']]]]],
                ['jsonrpc' => '2.0', 'method' => 'agent/messageStream', 'params' => ['content' => ['type' => 'text', 'text' => 'Done']]],
            ],
        );

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(ToolCallResult::class, $parts[0]);
        $toolCalls = $parts[0]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call-1', $toolCalls[0]->getId());
        $this->assertSame('read_file', $toolCalls[0]->getName());
        $this->assertSame(['content' => 'file content'], $toolCalls[0]->getArguments());

        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('Done', $parts[1]->getContent());
    }

    public function testConvertThrowsOnEmptyData()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult([]);

        $this->expectException(\Symfony\AI\Platform\Bridge\Acp\Exception\ProtocolException::class);
        $this->expectExceptionMessage('ACP did not return any result.');

        $converter->convert($rawResult);
    }

    public function testConvertThrowsOnNoSupportedContent()
    {
        $converter = new ResultConverter();
        $rawResult = new InMemoryRawResult(
            ['done' => true],
            [
                ['jsonrpc' => '2.0', 'method' => 'session/update', 'params' => ['update' => ['sessionUpdate' => 'plan', 'content' => 'some plan']]],
            ],
        );

        $this->expectException(\Symfony\AI\Platform\Bridge\Acp\Exception\ProtocolException::class);
        $this->expectExceptionMessage('ACP result does not contain any supported content.');

        $converter->convert($rawResult);
    }

    public function testGetTokenUsageExtractorReturnsExtractor()
    {
        $converter = new ResultConverter();

        $this->assertInstanceOf(TokenUsageExtractor::class, $converter->getTokenUsageExtractor());
    }
}
