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
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingContent;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
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
        $handler = new ResultConverter();

        $result = $handler->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame('toolu_01UM4PcTjC1UDiorSXVHSVFM', $result->getContent()[0]->getId());
        $this->assertSame('xxx_tool', $result->getContent()[0]->getName());
        $this->assertSame(['action' => 'get_data'], $result->getContent()[0]->getArguments());
    }

    public function testModelNotFoundError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"type":"error","error":{"type":"not_found_error","message":"model: claude-3-5-sonnet-20241022"}}'),
        ]);

        $response = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new ResultConverter();

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
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API Error [Unknown]: "An unknown error occurred."');

        $converter->convert(new RawHttpResult($response));
    }

    public function testStreamingToolCallsYieldsToolCallResult()
    {
        $converter = new ResultConverter();

        $httpResponse = self::createMock(ResponseInterface::class);
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

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallResult::class, $chunks[0]);

        $toolCalls = $chunks[0]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01ABC123', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testStreamingTextAndToolCallsYieldsBoth()
    {
        $converter = new ResultConverter();

        $httpResponse = self::createMock(ResponseInterface::class);
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

        $this->assertSame('Let me check ', $chunks[0]);
        $this->assertSame('the weather.', $chunks[1]);
        $this->assertInstanceOf(ToolCallResult::class, $chunks[2]);

        $toolCalls = $chunks[2]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01XYZ789', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Munich'], $toolCalls[0]->getArguments());
    }

    public function testStreamingThinkingBlockYieldsThinkingContent()
    {
        $converter = new ResultConverter();

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

        $this->assertCount(2, $chunks);

        $this->assertInstanceOf(ThinkingContent::class, $chunks[0]);
        $this->assertSame('Let me reason about this.', $chunks[0]->thinking);
        $this->assertSame('sig_abc123', $chunks[0]->signature);

        $this->assertSame('The answer is 42.', $chunks[1]);
    }

    public function testStreamingThinkingWithToolCallsYieldsBoth()
    {
        $converter = new ResultConverter();

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

        $this->assertCount(3, $chunks);

        $this->assertInstanceOf(ThinkingContent::class, $chunks[0]);
        $this->assertSame('I need to look this up.', $chunks[0]->thinking);
        $this->assertSame('sig_xyz', $chunks[0]->signature);

        $this->assertSame('Let me search.', $chunks[1]);

        $this->assertInstanceOf(ToolCallResult::class, $chunks[2]);
        $this->assertSame('toolu_01', $chunks[2]->getContent()[0]->getId());
        $this->assertSame('search', $chunks[2]->getContent()[0]->getName());
    }

    public function testStreamingThinkingWithoutSignature()
    {
        $converter = new ResultConverter();

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

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ThinkingContent::class, $chunks[0]);
        $this->assertSame('Quick thought.', $chunks[0]->thinking);
        $this->assertNull($chunks[0]->signature);
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
        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('The answer is 42.', $result->getContent());
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
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any text nor tool calls.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array<array<string, mixed>> $events
     */
    private function createRawResult(array $events): RawResultInterface
    {
        $httpResponse = self::createMock(ResponseInterface::class);
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
