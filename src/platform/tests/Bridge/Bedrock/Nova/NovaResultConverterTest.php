<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Bedrock\Nova;

use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Test\ResultMockFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class NovaResultConverterTest extends TestCase
{
    #[TestDox('Supports Nova model')]
    public function testSupports()
    {
        $converter = new NovaResultConverter();
        $this->assertTrue($converter->supports(new Nova('nova-pro')));
    }

    #[TestDox('Converts response with text content to TextResult')]
    public function testConvertTextResult()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'Hello from Nova!',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new NovaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello from Nova!', $result->getContent());
    }

    #[TestDox('Converts response with tool use to ToolCallResult')]
    public function testConvertToolCallResult()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'I will calculate this for you.',
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-123',
                                    'name' => 'calculate',
                                    'input' => ['expression' => '2+2'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new NovaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('nova-tool-123', $toolCalls[0]->getId());
        $this->assertSame('calculate', $toolCalls[0]->getName());
        $this->assertSame(['expression' => '2+2'], $toolCalls[0]->getArguments());
    }

    #[TestDox('Converts response with multiple tool calls to ToolCallResult')]
    public function testConvertMultipleToolCalls()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'I will help you with both requests.',
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-1',
                                    'name' => 'get_weather',
                                    'input' => ['location' => 'New York'],
                                ],
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-2',
                                    'name' => 'get_time',
                                    'input' => ['timezone' => 'EST'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new NovaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(2, $toolCalls);

        $this->assertSame('nova-tool-1', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['location' => 'New York'], $toolCalls[0]->getArguments());

        $this->assertSame('nova-tool-2', $toolCalls[1]->getId());
        $this->assertSame('get_time', $toolCalls[1]->getName());
        $this->assertSame(['timezone' => 'EST'], $toolCalls[1]->getArguments());
    }

    #[TestDox('Prioritizes tool calls over text in mixed content')]
    public function testConvertMixedContentWithToolUse()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'text' => 'I will calculate this for you.',
                            ],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'nova-tool-123',
                                    'name' => 'calculate',
                                    'input' => ['expression' => '5*10'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new NovaResultConverter();
        $result = $converter->convert($rawResult);

        // When tool calls are present, should return ToolCallResult regardless of text
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('nova-tool-123', $toolCalls[0]->getId());
    }

    #[TestDox('Throws RuntimeException when response has no output')]
    public function testConvertThrowsExceptionWhenNoOutput()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new NovaResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when response has empty output')]
    public function testConvertThrowsExceptionWhenEmptyOutput()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new NovaResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when content has no text')]
    public function testConvertThrowsExceptionWhenNoText()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [
                            [
                                'invalid' => 'data',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any text.');

        $converter = new NovaResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when message structure is missing')]
    public function testConvertThrowsExceptionWhenMissingMessageStructure()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any text.');

        $converter = new NovaResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when content array is missing')]
    public function testConvertThrowsExceptionWhenMissingContent()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'output' => [
                    'message' => [
                        'content' => [],
                    ],
                ],
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response content does not contain any text.');

        $converter = new NovaResultConverter();
        $converter->convert($rawResult);
    }
}
