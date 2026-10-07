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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ResultConverter;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
     * With `reasoning_effort: high`, Mistral streams the thinking trace inside an array-shaped
     * `delta.content` (a `thinking` chunk), then a transition chunk carrying the closing thinking
     * plus the first text chunk, then plain-string `content` for the answer.
     *
     * @see https://docs.mistral.ai/capabilities/reasoning/
     */
    public function testStreamingReasoningEffortHighEmitsThinkingThenTextDeltas()
    {
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Let me']]],
            ]], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => ' think.']]],
                ['type' => 'text', 'text' => 'The answer'],
            ]], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => ' is 391.'], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = iterator_to_array($streamResult->getContent(), false);

        $thinkingDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('Let me', $thinkingDeltas[0]->getThinking());
        $this->assertSame(' think.', $thinkingDeltas[1]->getThinking());

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('Let me think.', $thinkingCompletes[0]->getThinking());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(2, $textDeltas);
        $this->assertSame('The answer', $textDeltas[0]->getText());
        $this->assertSame(' is 391.', $textDeltas[1]->getText());

        $metadataDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof MetadataDelta));
        $this->assertCount(1, $metadataDeltas);
        $this->assertSame('finish_reason', $metadataDeltas[0]->getKey());
        $this->assertSame(FinishReasonCase::STOP, $metadataDeltas[0]->getValue()->getCase());

        // ThinkingComplete must precede the first TextDelta.
        $thinkingCompleteIndex = array_search($thinkingCompletes[0], $chunks, true);
        $firstTextDeltaIndex = array_search($textDeltas[0], $chunks, true);
        $this->assertLessThan($firstTextDeltaIndex, $thinkingCompleteIndex);
    }

    /**
     * Regression guard for the OpenAI-compatible path: with `reasoning_effort: none`, `delta.content`
     * is a plain string and must yield only text deltas.
     */
    public function testStreamingReasoningEffortNoneEmitsOnlyTextDeltas()
    {
        $converter = new ResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, '], 'finish_reason' => null]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!'], 'finish_reason' => 'stop']]],
        ];

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $this->httpResponseStub()), ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent(), false);

        $this->assertCount(0, array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(0, array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(2, $textDeltas);
        $this->assertSame('Hello, ', $textDeltas[0]->getText());
        $this->assertSame('world!', $textDeltas[1]->getText());

        $metadataDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof MetadataDelta));
        $this->assertCount(1, $metadataDeltas);
        $this->assertSame(FinishReasonCase::STOP, $metadataDeltas[0]->getValue()->getCase());
    }

    /**
     * With `reasoning_effort: high`, the buffered `message.content` is an array of thinking/text
     * chunks and must split into a {@see MultiPartResult} of {@see ThinkingResult} + {@see TextResult}.
     */
    public function testBufferedReasoningEffortHighSplitsThinkingAndText()
    {
        $converter = new ResultConverter();

        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Let me think.']]],
                            ['type' => 'text', 'text' => 'The answer is 391.'],
                        ],
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ];

        $result = $converter->convert(new InMemoryRawResult($data, [], $this->httpResponseStub()));

        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('Let me think.', $parts[0]->getContent());
        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('The answer is 391.', $parts[1]->getContent());
        $this->assertSame('The answer is 391.', $result->asText());

        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::STOP));
    }

    /**
     * A buffered response with a single thinking chunk (no text) must collapse to a lone
     * {@see ThinkingResult} rather than a {@see MultiPartResult}.
     */
    public function testBufferedReasoningEffortHighWithThinkingOnlyReturnsThinkingResult()
    {
        $converter = new ResultConverter();

        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'Still thinking...']]],
                        ],
                    ],
                    'finish_reason' => 'length',
                ],
            ],
        ];

        $result = $converter->convert(new InMemoryRawResult($data, [], $this->httpResponseStub()));

        $this->assertInstanceOf(ThinkingResult::class, $result);
        $this->assertSame('Still thinking...', $result->getContent());
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::LENGTH));
    }

    /**
     * Regression guard for the OpenAI-compatible path: a plain-string buffered `message.content`
     * must still produce a {@see TextResult}.
     */
    public function testBufferedReasoningEffortNoneReturnsTextResult()
    {
        $converter = new ResultConverter();

        $data = [
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello world'],
                    'finish_reason' => 'stop',
                ],
            ],
        ];

        $result = $converter->convert(new InMemoryRawResult($data, [], $this->httpResponseStub()));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testItConvertsABatchSubmissionIntoAJob()
    {
        $result = (new ResultConverter())->convert(self::batchResponse(), ['batch' => true]);

        $this->assertInstanceOf(JobResult::class, $result);

        $handle = $result->getContent();

        $this->assertSame('batch_123', $handle->getId());
        $this->assertSame('mistral', $handle->getProvider());
        $this->assertSame('mistral_batch', $handle->get('kind'));
        $this->assertSame('/v1/chat/completions', $handle->get('endpoint'));
        // Mistral's own default, since neither the job nor the invocation stated a timeout.
        $this->assertSame(86400, $handle->getMaxDuration());
        $this->assertSame(60.0, $handle->getPollInterval());
    }

    #[TestWith([6, 21600])]
    #[TestWith([24, 86400])]
    #[TestWith([null, 86400])]
    #[TestWith(['whenever', 86400])]
    public function testItCarriesTheTimeoutOfTheInvocationOnTheHandle(mixed $timeoutHours, int $expected)
    {
        $result = (new ResultConverter())->convert(self::batchResponse(), ['batch' => true, 'timeout_hours' => $timeoutHours]);

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame($expected, $result->getContent()->getMaxDuration());
    }

    public function testTheBatchHandleCarriesTheProviderNameTheConverterWasCreatedWith()
    {
        $result = (new ResultConverter('mistral-eu'))->convert(self::batchResponse(), ['batch' => true]);

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('mistral-eu', $result->getContent()->getProvider());
    }

    public function testItReportsABatchMistralRejects()
    {
        $response = (new MockHttpClient(new JsonMockResponse(['message' => 'Unsupported endpoint.'], ['http_code' => 422])))
            ->request('POST', 'https://api.mistral.ai/v1/batch/jobs');

        $this->expectException(RuntimeException::class);

        (new ResultConverter())->convert(new RawHttpResult($response), ['batch' => true]);
    }

    public function testItFailsWhenTheBatchResponseCarriesNoIdentifier()
    {
        $response = (new MockHttpClient(new JsonMockResponse(['status' => 'QUEUED'])))
            ->request('POST', 'https://api.mistral.ai/v1/batch/jobs');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Mistral response does not contain a batch identifier.');

        (new ResultConverter())->convert(new RawHttpResult($response), ['batch' => true]);
    }

    private static function batchResponse(): RawHttpResult
    {
        $response = (new MockHttpClient(new JsonMockResponse([
            'id' => 'batch_123',
            'object' => 'batch',
            'endpoint' => '/v1/chat/completions',
            'model' => 'mistral-large-latest',
            'status' => 'QUEUED',
        ])))->request('POST', 'https://api.mistral.ai/v1/batch/jobs');

        return new RawHttpResult($response);
    }

    private function httpResponseStub(): ResponseInterface
    {
        return (new MockHttpClient(new JsonMockResponse([], ['http_code' => 200])))
            ->request('POST', 'https://api.mistral.ai/v1/chat/completions');
    }
}
