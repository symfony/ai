<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Gpt;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\StreamChunk;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterStreamTest extends TestCase
{
    public function testStreamTextDeltas()
    {
        $sseBody = ''
            ."data: {\"choices\":[{\"delta\":{\"role\":\"assistant\"},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"Hello \"},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"world\"},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{},\"index\":0,\"finish_reason\":\"stop\"}]}\n\n"
            ."data: [DONE]\n\n";

        $mockClient = new MockHttpClient([
            new MockResponse($sseBody, [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'text/event-stream',
                ],
            ]),
        ]);
        $esClient = new EventSourceHttpClient($mockClient);
        $asyncResponse = $esClient->request('GET', 'http://localhost/stream');

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($asyncResponse), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        /** @var StreamChunk[] $chunks */
        $chunks = [];
        $content = '';

        foreach ($result->getContent() as $chunk) {
            $chunks[] = $chunk;
            $content .= $chunk;
        }

        // Only text deltas are yielded; role and finish chunks are ignored
        $this->assertSame('Hello world', $content);
        $this->assertCount(2, $chunks);
        $this->assertSame('Hello ', $chunks[0]->getContent());
        $this->assertEquals('http://localhost/stream', $chunks[0]->getRawResult()->getObject()->getInfo()['url']);
    }

    public function testStreamToolCallsAreAssembledAndYielded()
    {
        // Simulate a tool call that is streamed in multiple argument parts
        $sseBody = ''
            ."data: {\"choices\":[{\"delta\":{\"role\":\"assistant\"},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"tool_calls\":[{\"id\":\"call_123\",\"type\":\"function\",\"function\":{\"name\":\"test_function\",\"arguments\":\"{\\\"arg1\\\": \\\"value1\\\"}\"}}]},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{},\"index\":0,\"finish_reason\":\"tool_calls\"}]}\n\n"
            ."data: {\"usage\":{\"prompt_tokens\":1039,\"completion_tokens\":10,\"total_tokens\":1049,\"prompt_tokens_details\":{\"cached_tokens\":0,\"audio_tokens\":0},\"completion_tokens_details\":{\"reasoning_tokens\":0,\"audio_tokens\":0,\"accepted_prediction_tokens\":0,\"rejected_prediction_tokens\":0}}}\n\n"
            ."data: [DONE]\n\n";

        $mockClient = new MockHttpClient([
            new MockResponse($sseBody, [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'text/event-stream',
                ],
            ]),
        ]);
        $esClient = new EventSourceHttpClient($mockClient);
        $asyncResponse = $esClient->request('GET', 'http://localhost/stream');

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($asyncResponse), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $yielded = [];
        foreach ($result->getContent() as $delta) {
            $yielded[] = $delta;
        }

        // Expect only one yielded item and it should be a ToolCallResult
        $this->assertCount(1, $yielded);
        $this->assertInstanceOf(ToolCallResult::class, $yielded[0]);
        /** @var ToolCallResult $toolCallResult */
        $toolCallResult = $yielded[0];
        $toolCalls = $toolCallResult->getContent();

        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
        $this->assertSame(
            [
                'prompt_tokens' => 1039,
                'completion_tokens' => 10,
                'total_tokens' => 1049,
                'prompt_tokens_details' => [
                    'cached_tokens' => 0,
                    'audio_tokens' => 0,
                ],
                'completion_tokens_details' => [
                    'reasoning_tokens' => 0,
                    'audio_tokens' => 0,
                    'accepted_prediction_tokens' => 0,
                    'rejected_prediction_tokens' => 0,
                ],
            ],
            $toolCallResult->getMetadata()->get('usage')
        );
    }

    public function testStreamTokenUsage()
    {
        $sseBody = ''
            ."data: {\"choices\":[{\"delta\":{\"role\":\"assistant\"},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"Hello \"},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{\"content\":\"world\"},\"index\":0}]}\n\n"
            ."data: {\"choices\":[{\"delta\":{},\"index\":0,\"finish_reason\":\"stop\"}]}\n\n"
            ."data: {\"usage\":{\"prompt_tokens\":1039,\"completion_tokens\":10,\"total_tokens\":1049,\"prompt_tokens_details\":{\"cached_tokens\":0,\"audio_tokens\":0},\"completion_tokens_details\":{\"reasoning_tokens\":0,\"audio_tokens\":0,\"accepted_prediction_tokens\":0,\"rejected_prediction_tokens\":0}}}\n\n"
            ."data: [DONE]\n\n";

        $mockClient = new MockHttpClient([
            new MockResponse($sseBody, [
                'http_code' => 200,
                'response_headers' => [
                    'content-type' => 'text/event-stream',
                ],
            ]),
        ]);
        $esClient = new EventSourceHttpClient($mockClient);
        $asyncResponse = $esClient->request('GET', 'http://localhost/stream');

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($asyncResponse), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $yielded = [];
        foreach ($result->getContent() as $delta) {
            $yielded[] = $delta;
        }
        $this->assertCount(3, $yielded);
        $this->assertInstanceOf(TokenUsage::class, $yielded[2]);
        $this->assertSame(1039, $yielded[2]->promptTokens);
        $this->assertSame(10, $yielded[2]->completionTokens);
        $this->assertSame(1049, $yielded[2]->totalTokens);
        $this->assertSame(0, $yielded[2]->cachedTokens);
    }
}
