<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\DeepSeek;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;
use Symfony\AI\Platform\Bridge\DeepSeek\ResultConverter;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsDeepSeekModel()
    {
        $converter = new ResultConverter();
        $model = new DeepSeek('deepseek-chat');

        $this->assertTrue($converter->supports($model));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new ResultConverter();
        $model = new Model('gpt-4');

        $this->assertFalse($converter->supports($model));
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

        $httpResponse = $httpClient->request('POST', 'https://api.deepseek.com/chat/completions');
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

        $httpResponse = $httpClient->request('POST', 'https://api.deepseek.com/chat/completions');
        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame('call_abc123', $result->getContent()[0]->getId());
        $this->assertSame('get_weather', $result->getContent()[0]->getName());
        $this->assertSame(['location' => 'Paris'], $result->getContent()[0]->getArguments());
    }

    public function testConvertThrowsContentFilterException()
    {
        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content filtered');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'code' => 'content_filter',
                'message' => 'Content filtered',
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.deepseek.com/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsInvalidRequestException()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'code' => 'invalid_request_error',
                'message' => 'Invalid request',
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.deepseek.com/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }
}
