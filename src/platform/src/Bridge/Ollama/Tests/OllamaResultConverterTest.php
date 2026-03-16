<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\OllamaResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class OllamaResultConverterTest extends TestCase
{
    public function testSupportsLlamaModel()
    {
        $converter = new OllamaResultConverter();

        $this->assertTrue($converter->supports(new Ollama('llama3.2')));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testConvertTextResponse()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult([
            'message' => [
                'content' => 'Hello world',
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertToolCallResponse()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult([
            'message' => [
                'content' => 'This content will be ignored because tool_calls are present',
                'tool_calls' => [
                    [
                        'function' => [
                            'name' => 'test_function',
                            'arguments' => ['arg1' => 'value1'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('0', $toolCalls[0]->getId()); // ID is the array index as a string
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
    }

    public function testConvertMultipleToolCallsResponse()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult([
            'message' => [
                'content' => 'This content will be ignored because tool_calls are present',
                'tool_calls' => [
                    [
                        'function' => [
                            'name' => 'function1',
                            'arguments' => ['param1' => 'value1'],
                        ],
                    ],
                    [
                        'function' => [
                            'name' => 'function2',
                            'arguments' => ['param2' => 'value2'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(2, $toolCalls);

        $this->assertSame('0', $toolCalls[0]->getId());
        $this->assertSame('function1', $toolCalls[0]->getName());
        $this->assertSame(['param1' => 'value1'], $toolCalls[0]->getArguments());

        $this->assertSame('1', $toolCalls[1]->getId());
        $this->assertSame('function2', $toolCalls[1]->getName());
        $this->assertSame(['param2' => 'value2'], $toolCalls[1]->getArguments());
    }

    public function testThrowsExceptionWhenNoMessage()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain message');

        $converter->convert($rawResult);
    }

    public function testThrowsExceptionWhenNoContent()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult([
            'message' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Message does not contain content');

        $converter->convert($rawResult);
    }

    public function testItConvertsAResponseToAVectorResult()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('toArray')
            ->willReturn([
                'model' => 'all-minilm',
                'embeddings' => [
                    [0.3, 0.4, 0.4],
                    [0.0, 0.0, 0.2],
                ],
                'total_duration' => 14143917,
                'load_duration' => 1019500,
                'prompt_eval_count' => 8,
            ]);

        $vectorResult = (new OllamaResultConverter())->convert(new RawHttpResult($result));
        $convertedContent = $vectorResult->getContent();

        $this->assertCount(2, $convertedContent);

        $this->assertSame([0.3, 0.4, 0.4], $convertedContent[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $convertedContent[1]->getData());
    }

    public function testConvertStreamingResponse()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult(dataStream: $this->generateConvertStreamingStream());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = iterator_to_array($result->getContent());

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame(' world!', $chunks[1]->getText());
        $this->assertInstanceOf(TokenUsageInterface::class, $chunks[2]);
        $this->assertSame(42, $chunks[2]->getPromptTokens());
        $this->assertSame(17, $chunks[2]->getCompletionTokens());
    }

    public function testConvertThinkingStreamingResponse()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult(dataStream: $this->generateConvertThinkingStreamingStream());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = iterator_to_array($result->getContent());

        $this->assertCount(5, $chunks);
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[0]);
        $this->assertSame('Thinking', $chunks[0]->getThinking());
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[1]);
        $this->assertSame(' hard', $chunks[1]->getThinking());
        $this->assertInstanceOf(TextDelta::class, $chunks[2]);
        $this->assertSame('Hello', $chunks[2]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[3]);
        $this->assertSame(' world!', $chunks[3]->getText());
        $this->assertInstanceOf(TokenUsageInterface::class, $chunks[4]);
        $this->assertSame(42, $chunks[4]->getPromptTokens());
        $this->assertSame(17, $chunks[4]->getCompletionTokens());
    }

    public function testItPromotesTokenUsageMetadataFromStreamingResponse()
    {
        $deferredResult = new DeferredResult(
            new OllamaResultConverter(),
            new InMemoryRawResult(dataStream: $this->generateConvertStreamingStream()),
            ['stream' => true],
        );

        iterator_to_array($deferredResult->asStream());

        $this->assertInstanceOf(TokenUsageInterface::class, $tokenUsage = $deferredResult->getMetadata()->get('token_usage'));
        $this->assertSame(42, $tokenUsage->getPromptTokens());
        $this->assertSame(17, $tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }

    public function testConvertStreamingToolCallResponse()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult(dataStream: $this->generateConvertToolCallStreamingStream());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = iterator_to_array($result->getContent());

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('clock', $toolCalls[0]->getName());
        $this->assertSame(['timezone' => 'UTC'], $toolCalls[0]->getArguments());
        $this->assertInstanceOf(TokenUsageInterface::class, $chunks[1]);
        $this->assertSame(11, $chunks[1]->getPromptTokens());
        $this->assertSame(4, $chunks[1]->getCompletionTokens());
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function generateConvertStreamingStream(): iterable
    {
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.631700779Z', 'message' => ['role' => 'assistant', 'content' => 'Hello'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.905924913Z', 'message' => ['role' => 'assistant', 'content' => ' world!'], 'done' => true,
            'done_reason' => 'stop', 'total_duration' => 100, 'load_duration' => 10, 'prompt_eval_count' => 42, 'prompt_eval_duration' => 30, 'eval_count' => 17, 'eval_duration' => 60];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function generateConvertThinkingStreamingStream(): iterable
    {
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.631700779Z', 'message' => ['role' => 'assistant', 'content' => '', 'thinking' => 'Thinking'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:49.905924913Z', 'message' => ['role' => 'assistant', 'content' => '', 'thinking' => ' hard'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:50.14497475Z', 'message' => ['role' => 'assistant', 'content' => 'Hello'], 'done' => false];
        yield ['model' => 'deepseek-r1:latest', 'created_at' => '2025-10-29T17:15:50.367912083Z', 'message' => ['role' => 'assistant', 'content' => ' world!'], 'done' => true,
            'done_reason' => 'stop', 'total_duration' => 100, 'load_duration' => 10, 'prompt_eval_count' => 42, 'prompt_eval_duration' => 30, 'eval_count' => 17, 'eval_duration' => 60];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function generateConvertToolCallStreamingStream(): iterable
    {
        yield ['model' => 'llama3.2', 'created_at' => '2026-03-16T10:57:17.936041Z', 'message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'clock', 'arguments' => ['timezone' => 'UTC']]]]], 'done' => false];
        yield ['model' => 'llama3.2', 'created_at' => '2026-03-16T10:57:18.330845Z', 'message' => ['role' => 'assistant', 'content' => ''], 'done' => true,
            'done_reason' => 'stop', 'total_duration' => 100, 'load_duration' => 10, 'prompt_eval_count' => 11, 'prompt_eval_duration' => 30, 'eval_count' => 4, 'eval_duration' => 60];
    }
}
