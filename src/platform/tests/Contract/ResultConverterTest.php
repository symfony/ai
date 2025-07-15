<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\ResultConverter;
use Symfony\AI\Platform\Contract\ResultExtractor\VectorResultExtractor;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(ResultConverter::class)]
final class ResultConverterTest extends TestCase
{
    public function testStandardVectorResultConversion()
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/embeddings-standard-success.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/embeddings');

        $converter = ResultConverter::create();

        $actual = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $actual);
        $this->assertCount(1, $actual->getContent());
        $this->assertSame(5, $actual->getContent()[0]->getDimensions());
    }

    public function testSpecificVectorResultSuccess()
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/embeddings-specific-success.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/embeddings');

        $converter = ResultConverter::create([
            new VectorResultExtractor('$.embeddings[*].values'),
        ]);

        $actual = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(VectorResult::class, $actual);
        $this->assertCount(1, $actual->getContent());
        $this->assertSame(6, $actual->getContent()[0]->getDimensions());
    }

    public function testStandardTextResult()
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/openai-response-chat.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/completions');

        $converter = ResultConverter::create();

        $actual = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $actual);
        $this->assertSame('Arrr, matey! Symfony be a treasure of a framework for building web applications in PHP!', $actual->getContent());
    }

    public function testStandardToolCallResult()
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/openai-response-toolcall.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/completions');

        $converter = ResultConverter::create();

        $actual = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $actual);
        $this->assertContainsOnlyInstancesOf(ToolCall::class, $toolCalls = $actual->getContent());
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_1234', $toolCalls[0]->id);
        $this->assertSame('my_tool', $toolCalls[0]->name);
        $this->assertSame(['myParam' => 'abcdefg'], $toolCalls[0]->arguments);
    }

    public function testMultipleStandardToolCallResult()
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/openai-response-toolcall-multiple.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/completions');

        $converter = ResultConverter::create();

        $actual = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $actual);
        $this->assertContainsOnlyInstancesOf(ToolCall::class, $toolCalls = $actual->getContent());
        $this->assertCount(2, $toolCalls);
        $this->assertSame('call_1234', $toolCalls[0]->id);
        $this->assertSame('my_tool', $toolCalls[0]->name);
        $this->assertSame(['myParam' => 'abcdefg'], $toolCalls[0]->arguments);
        $this->assertSame('call_2345', $toolCalls[1]->id);
        $this->assertSame('foo_bar', $toolCalls[1]->name);
        $this->assertSame(['foo' => 'bar'], $toolCalls[1]->arguments);
    }

    public function testMultipleStandardToolCallChoiceResult()
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/openai-response-toolcall-choices.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/completions');

        $converter = ResultConverter::create();

        $actual = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ChoiceResult::class, $actual);
        $this->assertContainsOnlyInstancesOf(ToolCallResult::class, $toolCallResults = $actual->getContent());
        $this->assertCount(2, $toolCallResults);
        $this->assertCount(1, $toolCallResults[0]->getContent());
        $this->assertCount(1, $toolCallResults[1]->getContent());
        $this->assertSame('call_1234', $toolCallResults[0]->getContent()[0]->id);
        $this->assertSame('my_tool', $toolCallResults[0]->getContent()[0]->name);
        $this->assertSame(['myParam' => 'abcdefg'], $toolCallResults[0]->getContent()[0]->arguments);
        $this->assertSame('call_4321', $toolCallResults[1]->getContent()[0]->id);
        $this->assertSame('my_tool_two', $toolCallResults[1]->getContent()[0]->name);
        $this->assertSame(['foo' => 'bar'], $toolCallResults[1]->getContent()[0]->arguments);
    }

    public function testStandardChoicesResult()
    {
        $httpClient = new MockHttpClient($this->jsonMockResponseFromFile(__DIR__.'/fixtures/openai-response-choices.json'));
        $response = $httpClient->request('POST', 'https://api.example.com/v1/completions');

        $converter = ResultConverter::create();

        $actual = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ChoiceResult::class, $actual);
        $this->assertContainsOnlyInstancesOf(TextResult::class, $choices = $actual->getContent());
        $this->assertCount(3, $choices);
        $this->assertSame('1 Arrr, matey! Symfony be a treasure chest of tools fer building web applications in PHP!', $choices[0]->getContent());
        $this->assertSame('2 Arrr, my pirate! Symfony be a treasure chest of tools fer building web applications in PHP!', $choices[1]->getContent());
        $this->assertSame('3 Ahoy there, matey! Symfony be a treasure chest of a framework for PHP', $choices[2]->getContent());
    }

    /**
     * This can be replaced by `JsonMockResponse::fromFile` when dropping Symfony 6.4.
     */
    private function jsonMockResponseFromFile(string $file): JsonMockResponse
    {
        return new JsonMockResponse(json_decode(file_get_contents($file), true));
    }
}
