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
use Symfony\AI\Platform\Bridge\Ollama\OllamaMessageChunk;
use Symfony\AI\Platform\Bridge\Ollama\OllamaResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
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

        $chunks = $result->getContent();
        $this->assertInstanceOf(OllamaMessageChunk::class, $chunks->current());
        $this->assertSame('Hello', $chunks->current()->getContent());
        $this->assertFalse($chunks->current()->isDone());
        $this->assertSame('deepseek-r1:latest', $chunks->current()->raw['model']);
        $this->assertArrayNotHasKey('total_duration', $chunks->current()->raw);
        $chunks->next();
        $this->assertInstanceOf(OllamaMessageChunk::class, $chunks->current());
        $this->assertSame(' world!', $chunks->current()->getContent());
        $this->assertTrue($chunks->current()->isDone());
        $this->assertArrayHasKey('total_duration', $chunks->current()->raw);
    }

    public function testConvertThinkingStreamingResponse()
    {
        $converter = new OllamaResultConverter();
        $rawResult = new InMemoryRawResult(dataStream: $this->generateConvertThinkingStreamingStream());

        $result = $converter->convert($rawResult, options: ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = $result->getContent();
        $this->assertInstanceOf(OllamaMessageChunk::class, $chunks->current());
        $this->assertSame('', $chunks->current()->getContent());
        $this->assertSame('Thinking', $chunks->current()->getThinking());
        $this->assertFalse($chunks->current()->isDone());
        $this->assertSame('deepseek-r1:latest', $chunks->current()->raw['model']);
        $this->assertArrayNotHasKey('total_duration', $chunks->current()->raw);
        $chunks->next();
        $this->assertSame('', $chunks->current()->getContent());
        $this->assertSame(' hard', $chunks->current()->getThinking());
        $this->assertFalse($chunks->current()->isDone());
        $chunks->next();
        $this->assertSame('Hello', $chunks->current()->getContent());
        $this->assertNull($chunks->current()->getThinking());
        $this->assertFalse($chunks->current()->isDone());
        $chunks->next();
        $this->assertInstanceOf(OllamaMessageChunk::class, $chunks->current());
        $this->assertSame(' world!', $chunks->current()->getContent());
        $this->assertNull($chunks->current()->getThinking());
        $this->assertTrue($chunks->current()->isDone());
        $this->assertArrayHasKey('total_duration', $chunks->current()->raw);
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
}
