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
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
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

    public function testThrowsAuthenticationExceptionOnInvalidApiKey()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->with(false)->willReturn(json_encode([
            'error' => [
                'message' => 'invalid x-api-key',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid x-api-key');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponse()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->with(false)->willReturn(json_encode([
            'error' => [
                'message' => 'image exceeds max size',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('image exceeds max size');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponseWithNoResponseBody()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testStreamingToolCallsYieldsToolCallResult()
    {
        $converter = new ResultConverter();

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
        foreach ($streamResult->getContent(