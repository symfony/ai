<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
use Symfony\AI\Platform\Bridge\Perplexity\ResultConverter;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\MaxOutputTokensException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsPerplexityModelsOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Perplexity('fast')));
        $this->assertFalse($converter->supports(new ResponsesModel('fast')));
    }

    public function testConvertTextResult()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'status' => 'completed',
            'output' => [self::message('Hello world')],
        ]));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
        $this->assertFalse($result->getMetadata()->has('search_results'));
        $this->assertFalse($result->getMetadata()->has('citations'));
    }

    public function testConvertAttachesSearchResultsAndCitations()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'status' => 'completed',
            'output' => [
                self::searchResults([self::searchResult(1, 'https://example.com/paris'), self::searchResult(2, 'https://example.com/france')]),
                self::message('Paris is the capital of France.[1][2]'),
            ],
        ]));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Paris is the capital of France.[1][2]', $result->getContent());
        $this->assertSame([self::searchResult(1, 'https://example.com/paris'), self::searchResult(2, 'https://example.com/france')], $result->getMetadata()->get('search_results'));
        $this->assertSame(['https://example.com/paris', 'https://example.com/france'], $result->getMetadata()->get('citations'));
    }

    public function testConvertMergesSearchResultsOfEveryStep()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'status' => 'completed',
            'output' => [
                self::searchResults([self::searchResult(1, 'https://example.com/1')]),
                self::searchResults([self::searchResult(2, 'https://example.com/2')]),
                self::message('Answer.[1][2]'),
            ],
        ]));

        $this->assertSame(['https://example.com/1', 'https://example.com/2'], $result->getMetadata()->get('citations'));
    }

    public function testConvertKeepsCitationsReportedAsAnnotations()
    {
        $message = self::message('Answer.');
        $message['content'][0]['annotations'] = [['type' => 'url_citation', 'url' => 'https://example.com/cited', 'start_index' => 0, 'end_index' => 7]];

        $result = (new ResultConverter())->convert($this->createRawResult([
            'status' => 'completed',
            'output' => [self::searchResults([self::searchResult(1, 'https://example.com/1')]), $message],
        ]));

        $this->assertSame(['https://example.com/cited'], $result->getMetadata()->get('citations'));
    }

    public function testConvertSkipsOtherBuiltInToolOutputItems()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'status' => 'completed',
            'output' => [
                ['type' => 'fetch_url_results', 'contents' => [['url' => 'https://example.com', 'title' => 'Example', 'snippet' => '...']]],
                ['type' => 'finance_results', 'results' => []],
                ['type' => 'people_search_results', 'results' => []],
                ['type' => 'sandbox_results', 'results' => []],
                self::message('Done.'),
            ],
        ]));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Done.', $result->getContent());
    }

    public function testConvertToolCallResult()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'status' => 'completed',
            'output' => [[
                'type' => 'function_call',
                'id' => 'fc_1',
                'call_id' => 'call_1',
                'name' => 'get_weather',
                'arguments' => '{"city":"Paris"}',
                'status' => 'completed',
            ]],
        ]));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertSame('call_1', $result->getContent()[0]->getId());
        $this->assertSame(['city' => 'Paris'], $result->getContent()[0]->getArguments());
    }

    public function testConvertThrowsOnFailedResponse()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The model failed to answer.');

        (new ResultConverter())->convert($this->createRawResult([
            'status' => 'failed',
            'error' => ['message' => 'The model failed to answer.', 'type' => 'internal_error'],
            'output' => [],
        ]));
    }

    public function testConvertThrowsMaxOutputTokensExceptionOnTruncatedResponse()
    {
        $this->expectException(MaxOutputTokensException::class);

        (new ResultConverter())->convert($this->createRawResult([
            'status' => 'incomplete',
            'incomplete_details' => ['reason' => 'max_output_tokens'],
            'output' => [],
        ]));
    }

    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('The total length of all messages is too long.');

        (new ResultConverter())->convert($this->createRawResult([
            'error' => [
                'message' => 'The total length of all messages is too long.',
                'type' => 'too_many_prompt_tokens',
                'code' => 400,
            ],
        ], 400));
    }

    public function testConvertThrowsModelNotFoundExceptionOnNotFound()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Unknown model.');

        (new ResultConverter())->convert($this->createRawResult([
            'error' => ['message' => 'Unknown model.', 'type' => 'not_found', 'code' => 404],
        ], 404));
    }

    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(500);
        $httpResponse->method('getContent')->willReturn('Service Unavailable');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert(new RawHttpResult($httpResponse), ['stream' => true]);
    }

    public function testStreamingYieldsTextAndPromotesSearchResults()
    {
        $searchResults = self::searchResults([self::searchResult(1, 'https://example.com/paris')]);

        $deferredResult = new DeferredResult(new ResultConverter(), $this->createStreamingRawResult([
            ['type' => 'response.created', 'response' => ['status' => 'in_progress', 'output' => [], 'usage' => null]],
            ['type' => 'response.output_item.added', 'item' => ['type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'call_1', 'name' => 'search_web', 'arguments' => '{"queries":["capital of France"]}', 'status' => 'completed']],
            ['type' => 'response.reasoning.search_queries', 'call_id' => 'call_1', 'queries' => ['capital of France']],
            ['type' => 'response.output_item.done', 'item' => $searchResults],
            ['type' => 'response.output_item.added', 'item' => ['type' => 'message', 'role' => 'assistant', 'content' => []]],
            ['type' => 'response.output_text.delta', 'delta' => 'Paris is the capital'],
            ['type' => 'response.output_text.delta', 'delta' => ' of France.[1]'],
            ['type' => 'response.output_text.done', 'text' => 'Paris is the capital of France.[1]'],
            ['type' => 'response.completed', 'response' => ['status' => 'completed', 'output' => [$searchResults, self::message('Paris is the capital of France.[1]')]]],
        ]), ['stream' => true]);

        $deltas = iterator_to_array($deferredResult->asStream(), false);

        $this->assertCount(2, $deltas);
        $this->assertInstanceOf(TextDelta::class, $deltas[0]);
        $this->assertSame('Paris is the capital', $deltas[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $deltas[1]);
        $this->assertSame(' of France.[1]', $deltas[1]->getText());
        $this->assertSame([self::searchResult(1, 'https://example.com/paris')], $deferredResult->getMetadata()->get('search_results'));
        $this->assertSame(['https://example.com/paris'], $deferredResult->getMetadata()->get('citations'));
    }

    public function testStreamingKeepsFunctionCallsDeclaredInTheRequest()
    {
        $functionCall = ['type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'call_1', 'name' => 'get_weather', 'arguments' => '{"city":"Paris"}', 'status' => 'completed'];

        $deferredResult = new DeferredResult(new ResultConverter(), $this->createStreamingRawResult([
            ['type' => 'response.output_item.added', 'item' => $functionCall],
            ['type' => 'response.output_item.done', 'item' => $functionCall],
            ['type' => 'response.completed', 'response' => ['status' => 'completed', 'output' => [$functionCall]]],
        ]), ['stream' => true, 'tools' => [['type' => 'function', 'name' => 'get_weather', 'description' => 'Get the weather']]]);

        $deltas = iterator_to_array($deferredResult->asStream(), false);

        $this->assertInstanceOf(ToolCallStart::class, $deltas[0]);
        $this->assertSame('get_weather', $deltas[0]->getName());
        $this->assertInstanceOf(ToolCallComplete::class, $deltas[1]);
        $this->assertSame('call_1', $deltas[1]->getToolCalls()[0]->getId());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn($statusCode);
        $httpResponse->method('getContent')->willReturn(json_encode($data));
        $httpResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($httpResponse);
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function createStreamingRawResult(array $events): InMemoryRawResult
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        return new InMemoryRawResult(dataStream: $events, object: $httpResponse);
    }

    /**
     * @return array<string, mixed>
     */
    private static function message(string $text): array
    {
        return [
            'type' => 'message',
            'id' => 'msg_1',
            'role' => 'assistant',
            'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $results
     *
     * @return array<string, mixed>
     */
    private static function searchResults(array $results): array
    {
        return ['type' => 'search_results', 'queries' => ['capital of France'], 'results' => $results];
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchResult(int $id, string $url): array
    {
        return ['id' => $id, 'url' => $url, 'title' => 'Title '.$id, 'snippet' => 'Snippet '.$id, 'date' => '2026-06-10', 'source' => 'web'];
    }
}
