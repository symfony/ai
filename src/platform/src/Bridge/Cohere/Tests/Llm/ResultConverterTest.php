<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Llm\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsCohereModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Cohere('command-a-03-2025')));
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('Internal Server Error');

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 500');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItConvertsCompleteResponseToTextResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'COMPLETE',
            'message' => [
                'content' => [
                    ['type' => 'text', 'text' => 'Hello, world!'],
                ],
            ],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, world!', $result->getContent());
    }

    public function testItConvertsToolCallResponseToToolCallResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'TOOL_CALL',
            'message' => [
                'tool_calls' => [
                    [
                        'id' => 'call_123',
                        'function' => [
                            'name' => 'get_weather',
                            'arguments' => '{"city":"Paris"}',
                        ],
                    ],
                ],
            ],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertInstanceOf(ToolCall::class, $toolCalls[0]);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $toolCalls[0]->getArguments());
    }

    public function testItThrowsExceptionOnUnsupportedFinishReason()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'UNKNOWN',
            'message' => [],
        ]);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "UNKNOWN".');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItConvertsStreamWithTextContent()
    {
        $converter = new ResultConverter();
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => 'Hello']]]],
                ['type' => 'content-delta', 'delta' => ['message' => ['content' => ['text' => ', world!']]]],
                ['type' => 'message-end', 'delta' => []],
            ]),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertSame(', world!', $chunks[1]->getText());
    }

    public function testItConvertsStreamWithToolCalls()
    {
        $converter = new ResultConverter();
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'tool-call-start', 'delta' => ['message' => ['tool_calls' => ['id' => 'call_1', 'function' => ['name' => 'get_time', 'arguments' => '']]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => ['arguments' => '{"tz":']]]]],
                ['type' => 'tool-call-delta', 'delta' => ['message' => ['tool_calls' => ['function' => ['arguments' => '"UTC"}']]]]],
                ['type' => 'message-end', 'delta' => []],
            ]),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertSame('call_1', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame(['tz' => 'UTC'], $toolCalls[0]->getArguments());
    }

    public function testItConvertsToolCallWithEmptyArguments()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'finish_reason' => 'TOOL_CALL',
            'message' => [
                'tool_calls' => [
                    [
                        'id' => 'call_456',
                        'function' => [
                            'name' => 'get_time',
                            'arguments' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_456', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testItConvertsStreamWithToolCallsWithEmptyArguments()
    {
        $converter = new ResultConverter();
        $result = $converter->convert(
            new InMemoryRawResult([], [
                ['type' => 'tool-call-start', 'delta' => ['message' => ['tool_calls' => ['id' => 'call_1', 'function' => ['name' => 'get_time', 'arguments' => '']]]]],
                ['type' => 'message-end', 'delta' => []],
            ]),
            ['stream' => true],
        );

        $chunks = iterator_to_array($result->getContent(), false);
        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertSame('call_1', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame([], $toolCalls[0]->getArguments());
    }

    public function testGetTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertNotNull($converter->getTokenUsageExtractor());
    }
}
