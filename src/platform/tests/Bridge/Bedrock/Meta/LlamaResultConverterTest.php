<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Bedrock\Meta;

use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Test\ResultMockFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Meta\LlamaResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class LlamaResultConverterTest extends TestCase
{
    #[TestDox('Supports Llama model')]
    public function testSupports()
    {
        $model = new Llama('llama3-8b-instruct');

        $converter = new LlamaResultConverter();
        $this->assertTrue($converter->supports($model));
    }

    #[TestDox('Converts response with generation text to TextResult')]
    public function testConvertTextResult()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => 'Hello from Llama!',
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello from Llama!', $result->getContent());
    }

    #[TestDox('Converts empty generation to TextResult with empty content')]
    public function testConvertEmptyGeneration()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => '',
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
    }

    #[TestDox('Throws RuntimeException when generation field is null')]
    public function testConvertThrowsExceptionWhenGenerationIsNull()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => null,
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        // Looking at the LlamaResultConverter, it checks for isset() which returns false for null
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new LlamaResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Converts long generation text successfully')]
    public function testConvertLongGeneration()
    {
        $longText = str_repeat('This is a long text. ', 100);
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => $longText,
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame($longText, $result->getContent());
    }

    #[TestDox('Ignores additional response fields and extracts generation text')]
    public function testConvertWithAdditionalFields()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => 'Hello from Llama!',
                'prompt_token_count' => 10,
                'generation_token_count' => 5,
                'stop_reason' => 'end_turn',
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello from Llama!', $result->getContent());
    }

    #[TestDox('Throws RuntimeException when response has no generation field')]
    public function testConvertThrowsExceptionWhenNoGeneration()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new LlamaResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Throws RuntimeException when generation key is missing')]
    public function testConvertThrowsExceptionWhenGenerationKeyMissing()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'prompt_token_count' => 10,
                'generation_token_count' => 5,
                'stop_reason' => 'end_turn',
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter = new LlamaResultConverter();
        $converter->convert($rawResult);
    }

    #[TestDox('Converts response with options parameter successfully')]
    public function testConvertWithOptions()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => 'Hello with options!',
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult, ['some' => 'option']);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello with options!', $result->getContent());
    }

    #[TestDox('Converts numeric generation as string')]
    public function testConvertWithNumericGeneration()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => '42',
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('42', $result->getContent());
    }

    #[TestDox('Converts JSON string generation successfully')]
    public function testConvertWithJsonGeneration()
    {
        $invokeResponse = ResultMockFactory::create(InvokeModelResponse::class, [
            'body' => json_encode([
                'generation' => '{"response": "JSON formatted text"}',
            ]),
        ]);
        $rawResult = new RawBedrockResult($invokeResponse);

        $converter = new LlamaResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('{"response": "JSON formatted text"}', $result->getContent());
    }
}
