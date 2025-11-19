<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Bedrock\Anthropic;

use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Test\ResultMockFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\TextResult;
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

    #[TestDox('Converts response with multiple tool calls to ToolCallResult')]
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

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(2, $toolCalls);

        $this->assertSame('toolu_01', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'Paris'], $toolCalls[0]->getArguments());

        $this->assertSame('toolu_02', $toolCalls[1]->getId());
        $this->assertSame('get_time', $toolCalls[1]->getName());
        $this->assertSame(['timezone' => 'UTC'], $toolCalls[1]->getArguments());
    }

    #[TestDox('Prioritizes tool calls over text in mixed content')]
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

        // When tool calls are present, should return ToolCallResult regardless of text
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
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
        $this->expectExceptionMessage('Response content does not contain any text or type.');

        $converter = new ClaudeResultConverter();
        $converter->convert($rawResult);
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
