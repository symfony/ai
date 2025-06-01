<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAI\GPT;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAI\GPT\ResponseConverter;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Response\Choice;
use Symfony\AI\Platform\Response\ChoiceResponse;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\Response\ToolCall;
use Symfony\AI\Platform\Response\ToolCallResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(ResponseConverter::class)]
#[Small]
#[UsesClass(Choice::class)]
#[UsesClass(ChoiceResponse::class)]
#[UsesClass(TextResponse::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCallResponse::class)]
class ResponseConverterTest extends TestCase
{
    public function testConvertTextResponse(): void
    {
        $converter = new ResponseConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello world',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $response = $converter->convert($httpResponse);

        self::assertInstanceOf(TextResponse::class, $response);
        self::assertSame('Hello world', $response->getContent());
    }

    public function testConvertToolCallResponse(): void
    {
        $converter = new ResponseConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                    'arguments' => '{"arg1": "value1"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        $response = $converter->convert($httpResponse);

        self::assertInstanceOf(ToolCallResponse::class, $response);
        $toolCalls = $response->getContent();
        self::assertCount(1, $toolCalls);
        self::assertSame('call_123', $toolCalls[0]->id);
        self::assertSame('test_function', $toolCalls[0]->name);
        self::assertSame(['arg1' => 'value1'], $toolCalls[0]->arguments);
    }

    public function testConvertMultipleChoices(): void
    {
        $converter = new ResponseConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 1',
                    ],
                    'finish_reason' => 'stop',
                ],
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 2',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $response = $converter->convert($httpResponse);

        self::assertInstanceOf(ChoiceResponse::class, $response);
        $choices = $response->getContent();
        self::assertCount(2, $choices);
        self::assertSame('Choice 1', $choices[0]->getContent());
        self::assertSame('Choice 2', $choices[1]->getContent());
    }

    public function testContentFilterException(): void
    {
        $converter = new ResponseConverter();
        $httpResponse = self::createMock(ResponseInterface::class);

        $httpResponse->expects($this->exactly(2))
            ->method('toArray')
            ->willReturnCallback(function ($throw = true) {
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

        self::expectException(ContentFilterException::class);
        self::expectExceptionMessage('Content was filtered');

        $converter->convert($httpResponse);
    }

    public function testThrowsExceptionWhenNoChoices(): void
    {
        $converter = new ResponseConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Response does not contain choices');

        $converter->convert($httpResponse);
    }

    public function testThrowsExceptionForUnsupportedFinishReason(): void
    {
        $converter = new ResponseConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test content',
                    ],
                    'finish_reason' => 'unsupported_reason',
                ],
            ],
        ]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unsupported finish reason "unsupported_reason"');

        $converter->convert($httpResponse);
    }
}
