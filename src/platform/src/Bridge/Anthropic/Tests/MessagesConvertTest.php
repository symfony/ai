<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\MessagesClient;
use Symfony\AI\Platform\Bridge\Anthropic\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MessagesConvertTest extends TestCase
{
    public function testConvertThrowsExceptionWhenContentIsToolUseAndLacksText()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_01UM4PcTjC1UDiorSXVHSVFM',
                    'name' => 'xxx_tool',
                    'input' => ['action' => 'get_data'],
                ],
            ],
        ]));
        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $handler = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $result = $handler->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame('toolu_01UM4PcTjC1UDiorSXVHSVFM', $result->getContent()[0]->getId());
        $this->assertSame('xxx_tool', $result->getContent()[0]->getName());
        $this->assertSame(['action' => 'get_data'], $result->getContent()[0]->getArguments());
    }

    public function testConvertWithCodeExecutionReturnsMultiPartResult()
    {
        $json = file_get_contents(__DIR__.'/Fixtures/code_execution.json');
        $httpClient = new MockHttpClient(new JsonMockResponse(json_decode($json, true)));
        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $handler = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $result = $handler->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(6, $parts);

        $this->assertInstanceOf(TextResult::class, $parts[0]);
        $this->assertStringStartsWith("I'll calculate the total cost", $parts[0]->getContent());

        $this->assertInstanceOf(ExecutableCodeResult::class, $parts[1]);
        $this->assertSame('srvtoolu_0195ncSpA6Lt4HPtDGcuqh9y', $parts[1]->getId());
        $this->assertNull($parts[1]->getLanguage());
        $this->assertStringContainsString('Mortgage Calculator', $parts[1]->getContent());

        $this->assertInstanceOf(CodeExecutionResult::class, $parts[2]);
        $this->assertSame('srvtoolu_0195ncSpA6Lt4HPtDGcuqh9y', $parts[2]->getId());
        $this->assertTrue($parts[2]->isSucceeded());

        $this->assertInstanceOf(ExecutableCodeResult::class, $parts[3]);
        $this->assertSame('srvtoolu_01RpzxD68AoPFshMf1RNKjSg', $parts[3]->getId());
        $this->assertSame('bash', $parts[3]->getLanguage());
        $this->assertSame('python /tmp/mortgage_calculator.py', $parts[3]->getContent());

        $this->assertInstanceOf(CodeExecutionResult::class, $parts[4]);
        $this->assertSame('srvtoolu_01RpzxD68AoPFshMf1RNKjSg', $parts[4]->getId());
        $this->assertTrue($parts[4]->isSucceeded());
        $this->assertStringContainsString('MORTGAGE CALCULATION RESULTS', $parts[4]->getContent());

        $this->assertInstanceOf(TextResult::class, $parts[5]);
        $this->assertStringStartsWith('## Summary', $parts[5]->getContent());
    }

    public function testModelNotFoundError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"type":"error","error":{"type":"not_found_error","message":"model: claude-3-5-sonnet-20241022"}}'),
        ]);

        $response = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API Error [not_found_error]: "model: claude-3-5-sonnet-20241022"');

        $converter->convert(new RawHttpResult($response));
    }

    public function testUnknownError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"type":"error"}'),
        ]);

        $response = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API Error [Unknown]: "An unknown error occurred."');

        $converter->convert(new RawHttpResult($response));
    }

    public function testStreamingToolCallsYieldsToolCallResult()
    {
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_123', 'type' => 'message', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01ABC123', 'name' => 'get_weather']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"loc']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'ation": "']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'Berlin"}']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use']],
            ['type' => 'message_stop'],
        ];

        $raw = new class($httpResponse, $events) implements RawResultInterface {
            /**
             * @param array<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }

            public function getObject(): object
            {
                return $this->response;
            }
        };

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        // Expect: ToolCallStart, 3x ToolInputDelta, ToolCallComplete
        $this->assertInstanceOf(ToolCallStart::class, $chunks[0]);
        $this->assertSame('toolu_01ABC123', $chunks[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getName());
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[1]);
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[2]);
        $this->assertInstanceOf(ToolInputDelta::class, $chunks[3]);

        $toolCallComplete = $chunks[\count($chunks) - 1];
        $this->assertInstanceOf(ToolCallComplete::class, $toolCallComplete);
        $toolCalls = $toolCallComplete->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01ABC123', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testStreamingTextAndToolCallsYieldsBoth()
    {
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_123', 'type' => 'message', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Let me check ']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'the weather.']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01XYZ789', 'name' => 'get_weather']],
            ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"city": "Munich"}']],
            ['type' => 'content_block_stop', 'index' => 1],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use']],
            ['type' => 'message_stop'],
        ];

        $raw = new class($httpResponse, $events) implements RawResultInterface {
            /**
             * @param array<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }

            public function getObject(): object
            {
                return $this->response;
            }
        };

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        // Filter to get just text deltas and the final ToolCallComplete
        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertSame('Let me check ', $textDeltas[0]->getText());
        $this->assertSame('the weather.', $textDeltas[1]->getText());

        $toolCallComplete = $chunks[\count($chunks) - 1];
        $this->assertInstanceOf(ToolCallComplete::class, $toolCallComplete);

        $toolCalls = $toolCallComplete->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01XYZ789', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Munich'], $toolCalls[0]->getArguments());
    }

    public function testStreamingThinkingBlockYieldsThinkingComplete()
    {
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $events = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_123', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => 'Let me ']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => 'reason about this.']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'sig_abc']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => '123']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => 'The answer is 42.']],
            ['type' => 'content_block_stop', 'index' => 1],
            ['type' => 'message_stop'],
        ];

        $raw = $this->createRawResult($events);
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        // Expect: ThinkingStart, ThinkingDelta("Let me "), ThinkingDelta("reason about this."),
        //         ThinkingSignature("sig_abc"), ThinkingSignature("123"),
        //         ThinkingComplete (accumulated), TextDelta("The answer is 42.")
        $thinkingStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingStart));
        $this->assertCount(1, $thinkingStarts);

        $thinkingDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('Let me ', $thinkingDeltas[0]->getThinking());
        $this->assertSame('reason about this.', $thinkingDeltas[1]->getThinking());

        $signatures = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingSignature));
        $this->assertCount(2, $signatures);

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('Let me reason about this.', $thinkingCompletes[0]->getThinking());
        $this->assertSame('sig_abc123', $thinkingCompletes[0]->getSignature());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(1, $textDeltas);
        $this->assertSame('The answer is 42.', $textDeltas[0]->getText());
    }

    public function testStreamingThinkingWithToolCallsYieldsBoth()
    {
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $events = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_456', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => 'I need to look this up.']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'sig_xyz']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => 'Let me search.']],
            ['type' => 'content_block_stop', 'index' => 1],
            ['type' => 'content_block_start', 'index' => 2, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'search']],
            ['type' => 'content_block_delta', 'index' => 2, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"q":"test"}']],
            ['type' => 'content_block_stop', 'index' => 2],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use']],
            ['type' => 'message_stop'],
        ];

        $raw = $this->createRawResult($events);
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $thinkingStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingStart));
        $this->assertCount(1, $thinkingStarts);

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('I need to look this up.', $thinkingCompletes[0]->getThinking());
        $this->assertSame('sig_xyz', $thinkingCompletes[0]->getSignature());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(1, $textDeltas);
        $this->assertSame('Let me search.', $textDeltas[0]->getText());

        $toolCallComplete = $chunks[\count($chunks) - 1];
        $this->assertInstanceOf(ToolCallComplete::class, $toolCallComplete);
        $this->assertSame('toolu_01', $toolCallComplete->getToolCalls()[0]->getId());
        $this->assertSame('search', $toolCallComplete->getToolCalls()[0]->getName());
    }

    public function testStreamingThinkingWithoutSignature()
    {
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $events = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_789', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => 'Quick thought.']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => 'Done.']],
            ['type' => 'content_block_stop', 'index' => 1],
            ['type' => 'message_stop'],
        ];

        $raw = $this->createRawResult($events);
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $thinkingStarts = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingStart));
        $this->assertCount(1, $thinkingStarts);

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('Quick thought.', $thinkingCompletes[0]->getThinking());
        $this->assertNull($thinkingCompletes[0]->getSignature());
    }

    public function testConvertWithTextPreambleBeforeToolCallYieldsMultiPartResult()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Let me look that up for you.',
                ],
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_01ABC123',
                    'name' => 'get_weather',
                    'input' => ['location' => 'Berlin'],
                ],
            ],
        ]));
        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(TextResult::class, $parts[0]);
        $this->assertSame('Let me look that up for you.', $parts[0]->getContent());

        $this->assertInstanceOf(ToolCallResult::class, $parts[1]);
        $toolCalls = $parts[1]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01ABC123', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testNonStreamingResponseWithThinkingAndTextContent()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'content' => [
                [
                    'type' => 'thinking',
                    'thinking' => 'Let me reason about this...',
                    'signature' => 'sig_abc123',
                ],
                [
                    'type' => 'text',
                    'text' => 'The answer is 42.',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('Let me reason about this...', $parts[0]->getContent());
        $this->assertSame('sig_abc123', $parts[0]->getSignature());
        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('The answer is 42.', $parts[1]->getContent());
    }

    public function testNonStreamingResponseWithOnlyThinkingContent()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'content' => [
                [
                    'type' => 'thinking',
                    'thinking' => 'Reasoning only...',
                    'signature' => 'sig_xyz',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new MessagesClient(new HttpTransport(new MockHttpClient(), 'unused'));

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ThinkingResult::class, $result);
        $this->assertSame('Reasoning only...', $result->getContent());
        $this->assertSame('sig_xyz', $result->getSignature());
    }

    /**
     * @param array<array<string, mixed>> $events
     */
    private function createRawResult(array $events): RawResultInterface
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        return new class($httpResponse, $events) implements RawResultInterface {
            /**
             * @param array<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }

            public function getObject(): object
            {
                return $this->response;
            }
        };
    }
}
