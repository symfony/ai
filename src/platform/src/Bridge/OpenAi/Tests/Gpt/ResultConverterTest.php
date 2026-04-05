<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Gpt;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ResultConverterTest extends TestCase
{
    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Hello world',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertToolCallResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'function_call',
                    'id' => 'call_123',
                    'name' => 'test_function',
                    'arguments' => '{"arg1": "value1"}',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
    }

    public function testConvertMultipleChoices()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'role' => 'assistant',
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Choice 1',
                    ]],
                ],
                [
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Choice 2',
                    ]],
                    'type' => 'message',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ChoiceResult::class, $result);
        $output = $result->getContent();
        $this->assertCount(2, $output);
        $this->assertSame('Choice 1', $output[0]->getContent());
        $this->assertSame('Choice 2', $output[1]->getContent());
    }

    public function testContentFilterException()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);

        $httpResponse->expects($this->exactly(1))
            ->method('toArray')
            ->willReturnCallback(static function ($throw = true) {
                if ($throw) {
                    throw new class extends \Exception implements ClientExceptionInterface {
                        public function getResponse(): ResponseInterface
                        {
                            throw new RuntimeException('Not implemented');
                        }
                    };
                }

                return [
                    'error' => [
                        'code' => 'content_filter',
                        'message' => 'Content was filtered',
                    ],
                ];
            });

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content was filtered');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsAuthenticationExceptionOnInvalidApiKey()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Invalid API key provided: sk-invalid',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided: sk-invalid');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionWhenNoChoices()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponse()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Bad Request: invalid parameters',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request: invalid parameters');

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

    public function testThrowsDetailedErrorException()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'code' => 'invalid_request_error',
                'type' => 'invalid_request',
                'param' => 'model',
                'message' => 'The model `gpt-5` does not exist',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "invalid_request_error"-invalid_request (model): "The model `gpt-5` does not exist".');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testStreamTransmitsUsageToResultMetadata()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'message.delta.output_text.delta',
                'delta' => 'Hello',
            ],
            [
                'type' => 'message.delta.output_text.delta',
                'delta' => ' world',
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'usage' => [
                        'input_tokens' => 11,
                        'output_tokens' => 7,
                        'output_tokens_details' => [
                            'reasoning_tokens' => 2,
                        ],
                        'input_tokens_details' => [
                            'cached_tokens' => 3,
                        ],
                        'total_tokens' => 18,
                    ],
                    'output' => [],
                ],
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame(' world', $chunks[1]->getText());

        $this->assertInstanceOf(TokenUsage::class, $chunks[2]);
        $this->assertSame(11, $chunks[2]->getPromptTokens());
        $this->assertSame(7, $chunks[2]->getCompletionTokens());
        $this->assertSame(2, $chunks[2]->getThinkingTokens());
        $this->assertSame(3, $chunks[2]->getCachedTokens());
        $this->assertSame(18, $chunks[2]->getTotalTokens());
    }

    public function testStreamYieldsToolCallComplete()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [
                        [
                            'type' => 'function_call',
                            'id' => 'call_123',
                            'name' => 'get_weather',
                            'arguments' => '{"city":"Berlin"}',
                        ],
                    ],
                ],
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);

        $streamResult = $converter->convert($raw, ['stream' => true]);
        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(1, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertSame('call_123', $chunks[0]->getToolCalls()[0]->getId());
        $this->assertSame('get_weather', $chunks[0]->getToolCalls()[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $chunks[0]->getToolCalls()[0]->getArguments());
    }

    public function testStreamThrowsExceptionOnErrorEvent()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'error',
                'error' => [
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                    'message' => 'You exceeded your current quota',
                    'param' => null,
                ],
                'sequence_number' => 2,
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "insufficient_quota"-insufficient_quota (-): "You exceeded your current quota".');

        foreach ($streamResult->getContent() as $part) {
            // Iterate to trigger the generator
        }
    }
}
