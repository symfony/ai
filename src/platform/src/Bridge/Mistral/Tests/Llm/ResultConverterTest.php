<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ResultConverter;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsMistralModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Mistral('mistral-large-latest')));
    }

    /**
     * Not a cassette: provoking this for real means overflowing the smallest available Mistral
     * context window, so the recorded request body would be a ~640 KB prompt of filler text.
     */
    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('maximum context length');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'message' => 'Prompt contains 300019 tokens and 0 draft tokens, too large for model with 262144 maximum context length',
        ], ['http_code' => 400]));

        $httpResponse = $httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * Not a cassette: a provider cannot be asked for a 500 on demand. The assertion is on our own
     * status handling anyway - the body is irrelevant - so a mock is the honest tool here.
     */
    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['error' => 'Service Unavailable'], ['http_code' => 500]));
        $httpResponse = $httpClient->request('POST', 'https://example.com');
        $converter = new ResultConverter();

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert(new RawHttpResult($httpResponse), ['stream' => true]);
    }

    /**
     * Not a cassette: recording one would need a live reasoning run against a paid model. The payload
     * below is the documented thinking/text chunk shape Mistral streams for `reasoning_effort`.
     */
    public function testConvertStreamSplitsReasoningContentChunksIntoThinkingAndTextDeltas()
    {
        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => '23 times 17']]],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => ' is 391.']], 'closed' => true],
                ['type' => 'text', 'text' => 'The answer'],
            ]]]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => ' is 391.']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $httpClient = new MockHttpClient(new JsonMockResponse([]));
        $httpResponse = $httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions');

        $converter = new ResultConverter();
        $result = $converter->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);

        $deltas = iterator_to_array($result->getContent());

        $thinkingDeltas = array_values(array_filter($deltas, static fn ($delta) => $delta instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('23 times 17', $thinkingDeltas[0]->getThinking());
        $this->assertSame(' is 391.', $thinkingDeltas[1]->getThinking());

        $thinkingCompletes = array_values(array_filter($deltas, static fn ($delta) => $delta instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('23 times 17 is 391.', $thinkingCompletes[0]->getThinking());

        $textDeltas = array_values(array_filter($deltas, static fn ($delta) => $delta instanceof TextDelta));
        $this->assertCount(2, $textDeltas);
        $this->assertSame('The answer', $textDeltas[0]->getText());
        $this->assertSame(' is 391.', $textDeltas[1]->getText());
    }

    /**
     * Not a cassette: see {@see testConvertStreamSplitsReasoningContentChunksIntoThinkingAndTextDeltas}.
     */
    public function testConvertSplitsAReasoningMessageIntoAThinkingAndATextPart()
    {
        $result = $this->convertMessageContent([
            ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => '23 times 17 is 391.']], 'signature' => 'sig'],
            ['type' => 'text', 'text' => 'The answer is 391.'],
        ]);

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::STOP));

        [$thinking, $text] = $result->getContent();

        $this->assertInstanceOf(ThinkingResult::class, $thinking);
        $this->assertSame('23 times 17 is 391.', $thinking->getContent());
        $this->assertSame('sig', $thinking->getSignature());

        $this->assertInstanceOf(TextResult::class, $text);
        $this->assertSame('The answer is 391.', $text->getContent());
    }

    /**
     * Not a cassette: see {@see testConvertStreamSplitsReasoningContentChunksIntoThinkingAndTextDeltas}.
     */
    public function testConvertReturnsThinkingOnlyWhenAReasoningMessageCarriesNoText()
    {
        $result = $this->convertMessageContent([
            ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => '23 times 17']]],
        ], 'length');

        $this->assertInstanceOf(ThinkingResult::class, $result);
        $this->assertSame('23 times 17', $result->getContent());
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::LENGTH));
    }

    /**
     * @param list<array<string, mixed>> $content
     */
    private function convertMessageContent(array $content, string $finishReason = 'stop'): ResultInterface
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $content],
            'finish_reason' => $finishReason,
        ]]]));

        $httpResponse = $httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions');

        return (new ResultConverter())->convert(new RawHttpResult($httpResponse));
    }
}
