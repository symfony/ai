<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Anthropic;

use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Test\ResultMockFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ClaudeResultConverterTest extends TestCase
{
    #[TestDox('Supports Claude model')]
    public function testSupports()
    {
        $converter = new ClaudeResultConverter();
        $model = new Claude('claude-3-5-sonnet-20241022');

        $this->assertTrue($converter->supports($model));
    }

    #[TestDox('Converts response with text content to TextResult')]
    public function testConvertTextResult()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello, world!',
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, world!', $result->getContent());
    }

    #[TestDox('Converts response with tool use to ToolCallResult')]
    public function testConvertToolCallResult()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_01UM4PcTjC1UDiorSXVHSVFM',
                        'name' => 'get_weather',
                        'input' => ['location' => 'Paris'],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01UM4PcTjC1UDiorSXVHSVFM', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Paris'], $toolCalls[0]->getArguments());
    }

    #[TestDox('Converts response with multiple tool calls to MultiPartResult with one ToolCallResult per call')]
    public function testConvertMultipleToolCalls()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_01',
                        'name' => 'get_weather',
                        'input' => ['location' => 'Paris'],
                    ],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_02',
                        'name' => 'get_time',
                        'input' => ['timezone' => 'UTC'],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(ToolCallResult::class, $parts[0]);
        $toolCalls = $parts[0]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Paris'], $toolCalls[0]->getArguments());

        $this->assertInstanceOf(ToolCallResult::class, $parts[1]);
        $toolCalls = $parts[1]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_02', $toolCalls[0]->getId());
        $this->assertSame('get_time', $toolCalls[0]->getName());
        $this->assertSame(['timezone' => 'UTC'], $toolCalls[0]->getArguments());
    }

    #[TestDox('Converts mixed text and tool use content to MultiPartResult')]
    public function testConvertMixedContentWithToolUse()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'I will get the weather for you.',
                    ],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_01',
                        'name' => 'get_weather',
                        'input' => ['location' => 'Paris'],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(TextResult::class, $parts[0]);
        $this->assertSame('I will get the weather for you.', $parts[0]->getContent());

        $this->assertInstanceOf(ToolCallResult::class, $parts[1]);
        $toolCalls = $parts[1]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01', $toolCalls[0]->getId());
    }

    #[TestDox('Throws RuntimeException when response has no content')]
    public function testConvertThrowsExceptionWhenNoContent()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new ClaudeResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when response has empty content array')]
    public function testConvertThrowsExceptionWhenEmptyContent()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new ClaudeResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when content has no text or type field')]
    public function testConvertThrowsExceptionWhenNoTextOrType()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'invalid' => 'data',
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any supported content.');

        $converter = new ClaudeResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Converts thinking-led response to MultiPartResult with thinking and text parts')]
    public function testConvertThinkingLedResponse()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'thinking',
                        'thinking' => '',
                        'signature' => 'abc',
                    ],
                    [
                        'type' => 'text',
                        'text' => '{"result": 1}',
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('', $parts[0]->getContent());
        $this->assertSame('abc', $parts[0]->getSignature());

        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('{"result": 1}', $parts[1]->getContent());

        // Only text parts are flattened; the empty thinking content must not leak into the text
        $this->assertSame('{"result": 1}', $result->asText());
    }

    #[TestDox('Converts thinking-led tool use response to MultiPartResult with thinking and tool call parts')]
    public function testConvertThinkingLedToolUseResponse()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'thinking',
                        'thinking' => '',
                        'signature' => 'abc',
                    ],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_01',
                        'name' => 'get_weather',
                        'input' => ['location' => 'Paris'],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);

        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);

        $this->assertInstanceOf(ToolCallResult::class, $parts[1]);
        $toolCalls = $parts[1]->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('toolu_01', $toolCalls[0]->getId());
    }

    #[TestDox('Converts thinking-only response to ThinkingResult')]
    public function testConvertThinkingOnlyResponse()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'thinking',
                        'thinking' => '',
                        'signature' => 'abc',
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ThinkingResult::class, $result);
        $this->assertSame('', $result->getContent());
        $this->assertSame('abc', $result->getSignature());
    }

    #[TestDox('Converts text content successfully')]
    public function testConvertWithValidTypeButNoText()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Valid text content',
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new ClaudeResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Valid text content', $result->getContent());
    }
}
