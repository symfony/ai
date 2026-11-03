<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\ModelClient;
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\ToolSearch;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\ToolSearchComplete;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\ToolSearchResult;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ToolSearchTest extends TestCase
{
    public function testNormalizerMarksDeferredToolsOnly()
    {
        $normalizer = new ToolNormalizer();

        $this->assertArrayNotHasKey('defer_loading', $normalizer->normalize($this->tool('get_weather')));
        $this->assertTrue($normalizer->normalize($this->tool('get_weather', true))['defer_loading']);
    }

    public function testRequestAddsSearchToolAndKeepsDeferLoading()
    {
        $body = $this->sendRequest('claude-sonnet-4-5', [$this->normalized('clock'), $this->normalized('get_weather', true)]);

        $this->assertSame([
            ['name' => 'clock', 'description' => 'd', 'input_schema' => ['type' => 'object'], 'cache_control' => ['type' => 'ephemeral']],
            ['name' => 'get_weather', 'description' => 'd', 'input_schema' => ['type' => 'object'], 'defer_loading' => true],
            ['type' => 'tool_search_tool_bm25_20251119', 'name' => 'tool_search_tool_bm25'],
        ], $body['tools']);
    }

    public function testRequestSupportsTheRegexSearch()
    {
        $body = $this->sendRequest('claude-opus-5-5', [$this->normalized('clock'), $this->normalized('get_weather', true)], ['tool_search' => 'regex']);

        $this->assertSame(['type' => 'tool_search_tool_regex_20251119', 'name' => 'tool_search_tool_regex'], $body['tools'][2]);
        $this->assertArrayNotHasKey('tool_search', $body);
    }

    public function testRequestRejectsUnknownToolSearch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported tool search "semantic"');

        $this->sendRequest('claude-sonnet-4-5', [$this->normalized('clock')], ['tool_search' => 'semantic']);
    }

    public function testRequestWithoutDeferredToolsAddsNoSearchTool()
    {
        $body = $this->sendRequest('claude-sonnet-4-5', [$this->normalized('clock')]);

        $this->assertCount(1, $body['tools']);
    }

    public function testRequestDoesNotAddASecondSearchTool()
    {
        $search = ['type' => 'tool_search_tool_regex_20251119', 'name' => 'tool_search_tool_regex'];
        $body = $this->sendRequest('claude-sonnet-4-5', [$this->normalized('clock'), $this->normalized('get_weather', true), $search]);

        $this->assertCount(3, $body['tools']);
    }

    public function testCacheControlSkipsDeferredTools()
    {
        $body = $this->sendRequest('claude-sonnet-4-5', [$this->normalized('get_weather', true), $this->normalized('clock'), $this->normalized('get_news', true)]);

        $this->assertArrayNotHasKey('cache_control', $body['tools'][0]);
        $this->assertArrayHasKey('cache_control', $body['tools'][1]);
        $this->assertArrayNotHasKey('cache_control', $body['tools'][2]);
    }

    #[DataProvider('modelsWithoutToolSearch')]
    public function testModelsWithoutToolSearchGetDeferredToolsAsNormalTools(string $model)
    {
        $body = $this->sendRequest($model, [$this->normalized('clock'), $this->normalized('get_weather', true)]);

        $this->assertCount(2, $body['tools']);
        $this->assertArrayNotHasKey('defer_loading', $body['tools'][1]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function modelsWithoutToolSearch(): iterable
    {
        yield 'claude 3.5 sonnet' => ['claude-3-5-sonnet-latest'];
        yield 'claude sonnet 4' => ['claude-sonnet-4-20250514'];
        yield 'claude sonnet 4.0 alias' => ['claude-sonnet-4-0'];
        yield 'claude opus 4.1' => ['claude-opus-4-1'];
    }

    public function testBufferedSearchMergesCallAndResultIntoOneResult()
    {
        $call = ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']];
        $found = ['type' => 'tool_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => [
            'type' => 'tool_search_tool_search_result',
            'tool_references' => [['type' => 'tool_reference', 'tool_name' => 'get_weather']],
        ]];

        $result = (new ResultConverter())->convert($this->httpResult([
            $call,
            $found,
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Berlin']],
        ]));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(ToolSearchResult::class, $parts[0]);
        $this->assertSame('weather', $parts[0]->getQuery());
        $this->assertSame('srvtoolu_1', $parts[0]->getId());
        $this->assertSame('completed', $parts[0]->getStatus());
        $this->assertSame(['get_weather'], $parts[0]->getToolNames());
        $this->assertSame(json_encode([$call, $found]), $parts[0]->getSignature());
        $this->assertInstanceOf(ToolCallResult::class, $parts[1]);
        $this->assertSame('get_weather', $parts[1]->getContent()[0]->getName());
    }

    public function testBufferedRegexSearchReadsThePattern()
    {
        $result = (new ResultConverter())->convert($this->httpResult([
            ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_regex', 'input' => ['pattern' => 'weather|news']],
            ['type' => 'tool_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => ['type' => 'tool_search_tool_search_result', 'tool_references' => []]],
            ['type' => 'text', 'text' => 'Nothing found.'],
        ]));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $search = $result->getContent()[0];
        $this->assertInstanceOf(ToolSearchResult::class, $search);
        $this->assertSame('weather|news', $search->getQuery());
        $this->assertSame([], $search->getToolNames());
    }

    public function testBufferedSearchErrorIsReportedAsStatus()
    {
        $result = (new ResultConverter())->convert($this->httpResult([
            ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']],
            ['type' => 'tool_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => ['type' => 'tool_search_tool_result_error', 'error_code' => 'unavailable']],
        ]));

        $this->assertInstanceOf(ToolSearchResult::class, $result);
        $this->assertSame('unavailable', $result->getStatus());
    }

    public function testStreamedSearchMergesCallAndResultIntoOneDelta()
    {
        $deltas = $this->toolSearchDeltas([
            ['type' => 'message_start', 'message' => ['id' => 'msg_1', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => []]],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"query":"wea']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'ther"}']],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'content_block_start', 'index' => 1, 'content_block' => [
                'type' => 'tool_search_tool_result',
                'tool_use_id' => 'srvtoolu_1',
                'content' => ['type' => 'tool_search_tool_search_result', 'tool_references' => [['type' => 'tool_reference', 'tool_name' => 'get_weather']]],
            ]],
            ['type' => 'content_block_stop', 'index' => 1],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn']],
            ['type' => 'message_stop'],
        ]);

        $this->assertCount(1, $deltas);
        $this->assertSame('weather', $deltas[0]->getQuery());
        $this->assertSame('srvtoolu_1', $deltas[0]->getId());
        $this->assertSame('completed', $deltas[0]->getStatus());
        $this->assertSame(['get_weather'], $deltas[0]->getToolNames());

        $blocks = json_decode($deltas[0]->getSignature(), true);
        $this->assertSame(['server_tool_use', 'tool_search_tool_result'], array_column($blocks, 'type'));
        $this->assertSame(['query' => 'weather'], $blocks[0]['input']);
    }

    public function testStreamedSearchWithoutResultStillYieldsTheCall()
    {
        $deltas = $this->toolSearchDeltas([
            ['type' => 'message_start', 'message' => ['id' => 'msg_1', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']]],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'pause_turn']],
            ['type' => 'message_stop'],
        ]);

        $this->assertCount(1, $deltas);
        $this->assertSame('weather', $deltas[0]->getQuery());
        $this->assertNull($deltas[0]->getStatus());
        $this->assertSame([], $deltas[0]->getToolNames());
    }

    public function testNormalizerReplaysBothBlocksOfASearchInWireOrder()
    {
        $call = ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']];
        $found = ['type' => 'tool_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => ['type' => 'tool_search_tool_search_result', 'tool_references' => [['type' => 'tool_reference', 'tool_name' => 'get_weather']]]];

        $normalized = (new AssistantMessageNormalizer())->normalize(new AssistantMessage(
            new ToolSearch('weather', 'srvtoolu_1', 'completed', ['get_weather'], json_encode([$call, $found])),
            new Text('Looking it up.'),
        ));

        $this->assertSame([
            'role' => 'assistant',
            'content' => [$call, $found, ['type' => 'text', 'text' => 'Looking it up.']],
        ], $normalized);
    }

    #[DataProvider('unreplayableSignatures')]
    public function testNormalizerDropsASearchItCannotReplay(?string $signature)
    {
        $normalized = (new AssistantMessageNormalizer())->normalize(new AssistantMessage(
            new ToolSearch('weather', 'srvtoolu_1', 'completed', [], $signature),
            new Text('Looking it up.'),
        ));

        $this->assertSame([['type' => 'text', 'text' => 'Looking it up.']], $normalized['content']);
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function unreplayableSignatures(): iterable
    {
        yield 'no signature' => [null];
        yield 'not JSON' => ['not json'];
        yield 'not a list of blocks' => ['"a string"'];
        yield 'a block of another feature' => ['[{"type":"server_tool_use","name":"web_search"}]'];
    }

    /**
     * @param list<array<string, mixed>> $tools
     * @param array<string, mixed>       $options
     *
     * @return array<string, mixed>
     */
    private function sendRequest(string $model, array $tools, array $options = []): array
    {
        $body = null;
        $client = new ModelClient(new MockHttpClient(static function ($method, $url, $requestOptions) use (&$body) {
            $body = json_decode($requestOptions['body'], true);

            return new JsonMockResponse('{}');
        }), 'key');

        $client->request(new Claude($model), ['messages' => []], ['tools' => $tools] + $options);

        return $body;
    }

    private function tool(string $name, bool $deferred = false): Tool
    {
        return new Tool(new ExecutionReference('Tool'), $name, 'd', deferred: $deferred);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(string $name, bool $deferred = false): array
    {
        return (new ToolNormalizer())->normalize($this->tool($name, $deferred));
    }

    /**
     * @param list<array<string, mixed>> $content
     */
    private function httpResult(array $content): RawHttpResult
    {
        $client = new MockHttpClient(new JsonMockResponse(['content' => $content, 'stop_reason' => 'end_turn']));

        return new RawHttpResult($client->request('POST', 'https://api.anthropic.com/v1/messages'));
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return list<ToolSearchResult>
     */
    private function toolSearchDeltas(array $events): array
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $searches = [];
        foreach ((new ResultConverter())->convert(new InMemoryRawResult([], $events, $response), ['stream' => true])->getContent() as $delta) {
            if ($delta instanceof ToolSearchComplete) {
                $searches[] = $delta->getToolSearch();
            }
        }

        return $searches;
    }
}
