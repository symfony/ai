<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cerebras\Model;
use Symfony\AI\Platform\Bridge\Cerebras\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Model('gpt-oss-120b')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new BaseModel('gpt-4')));
    }

    public function testConvertTextResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello, how can I help you?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, how can I help you?', $result->getContent());
    }

    public function testConvertToolCallResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":"Paris"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame('call_abc123', $result->getContent()[0]->getId());
        $this->assertSame('get_weather', $result->getContent()[0]->getName());
        $this->assertSame(['location' => 'Paris'], $result->getContent()[0]->getArguments());
    }

    public function testConvertMultipleToolCallsResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":"Paris"}',
                                ],
                            ],
                            [
                                'id' => 'call_def456',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":"London"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertSame('call_abc123', $result->getContent()[0]->getId());
        $this->assertSame('get_weather', $result->getContent()[0]->getName());
        $this->assertSame(['location' => 'Paris'], $result->getContent()[0]->getArguments());
        $this->assertSame('call_def456', $result->getContent()[1]->getId());
        $this->assertSame('get_weather', $result->getContent()[1]->getName());
        $this->assertSame(['location' => 'London'], $result->getContent()[1]->getArguments());
    }

    public function testConvertThrowsOnApiError()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cerebras API error: "Invalid API key"');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'type' => 'authentication_error',
            'message' => 'Invalid API key',
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsOnMissingChoices()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output.');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'id' => 'chatcmpl-123',
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsOnUnsupportedFinishReason()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "content_filter".');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                    ],
                    'finish_reason' => 'content_filter',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }
}
