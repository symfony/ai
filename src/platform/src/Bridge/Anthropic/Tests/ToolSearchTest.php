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
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Anthropic\ModelCatalog;
use Symfony\AI\Platform\Bridge\Anthropic\ModelClient;
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\ServerToolCall;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ServerToolCallResult;
use Symfony\AI\Platform\Result\Stream\Delta\ServerToolCallComplete;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ToolSearchTest extends TestCase
{
    public function testNormalizerMarksDeferredToolsOnly()
    {
        $this->assertArrayNotHasKey('defer_loading', $this->normalized('get_weather'));
        $this->assertTrue($this->normalized('get_weather', true)['defer_loading']);
    }

    #[DataProvider('modelsWithoutToolSearch')]
    public function testNormalizerSendsDeferredToolsAsNormalToolsToModelsWithoutToolSearch(string $model)
    {
        $this->assertArrayNotHasKey('defer_loading', $this->normalized('get_weather', true, $model));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function modelsWithoutToolSearch(): iterable
    {
        yield 'claude 3.5 sonnet' => ['claude-3-5-sonnet-20241022'];
        yield 'claude sonnet 4' => ['claude-sonnet-4-20250514'];
        yield 'claude sonnet 4.0 alias' => ['claude-sonnet-4-0'];
        yield 'claude opus 4.1' => ['claude-opus-4-1'];
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

    public function testRequestSupportsTheRegexSearchAsServerTool()
    {
        $body = $this->sendRequest('claude-opus-5-5', [$this->normalized('clock'), $this->normalized('get_weather', true)], ['server_tools' => ['tool_search_tool_regex' => true]]);

        $this->assertCount(3, $body['tools']);
        $this->assertSame(['type' => 'tool_search_tool_regex_20251119', 'name' => 'tool_search_tool_regex'], $body['tools'][2]);
        $this->assertArrayNotHasKey('server_tools', $body);
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

    public function testRequestKeepsReplayedSearchesWhoseToolsAreStillDefined()
    {
        $messages = $this->messagesWithSearch(['get_weather']);

        $body = $this->sendRequest('claude-sonnet-4-5', [$this->normalized('clock'), $this->normalized('get_weather', true)], messages: $messages);

        $this->assertSame(['server_tool_use', 'tool_search_tool_result', 'text'], array_column($body['messages'][0]['content'], 'type'));
    }

    public function testRequestDropsReplayedSearchesWhoseToolsAreNoLongerDefined()
    {
        $body = $this->sendRequest('claude-sonnet-4-5', [$this->normalized('clock')], messages: $this->messagesWithSearch(['get_weather']));

        $this->assertSame(['text'], array_column($body['messages'][0]['content'], 'type'));
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
        $this->assertInstanceOf(ServerToolCallResult::class, $parts[0]);
        $this->assertSame('tool_search', $parts[0]->getName());
        $this->assertSame('srvtoolu_1', $parts[0]->getId());
        $this->assertSame('completed', $parts[0]->getStatus());
        $this->assertSame(['query' => 'weather', 'tools' => ['get_weather']], $parts[0]->getDetails());
        $this->assertSame(json_encode([$call, $found]), $parts[0]->getReplay());
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
        $this->assertInstanceOf(ServerToolCallResult::class, $search);
        $this->assertSame(['query' => 'weather|news', 'tools' => []], $search->getDetails());
    }

    public function testBufferedSearchErrorIsReportedAsStatus()
    {
        $result = (new ResultConverter())->convert($this->httpResult([
            ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']],
            ['type' => 'tool_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => ['type' => 'tool_search_tool_result_error', 'error_code' => 'unavailable']],
        ]));

        $this->assertInstanceOf(ServerToolCallResult::class, $result);
        $this->assertSame('unavailable', $result->getStatus());
    }

    public function testStreamedSearchMergesCallAndResultIntoOneDelta()
    {
        $calls = $this->serverToolCalls([
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

        $this->assertCount(1, $calls);
        $this->assertSame('tool_search', $calls[0]->getName());
        $this->assertSame('srvtoolu_1', $calls[0]->getId());
        $this->assertSame('completed', $calls[0]->getStatus());
        $this->assertSame(['query' => 'weather', 'tools' => ['get_weather']], $calls[0]->getDetails());

        $blocks = json_decode($calls[0]->getReplay(), true);
        $this->assertSame(['server_tool_use', 'tool_search_tool_result'], array_column($blocks, 'type'));
        $this->assertSame(['query' => 'weather'], $blocks[0]['input']);
    }

    public function testStreamedSearchWithoutResultStillYieldsTheCall()
    {
        $calls = $this->serverToolCalls([
            ['type' => 'message_start', 'message' => ['id' => 'msg_1', 'role' => 'assistant', 'content' => []]],
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']]],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'message_delta', 'delta' => ['stop_reason' => 'pause_turn']],
            ['type' => 'message_stop'],
        ]);

        $this->assertCount(1, $calls);
        $this->assertSame(['query' => 'weather'], $calls[0]->getDetails());
        $this->assertNull($calls[0]->getStatus());
    }

    public function testNormalizerReplaysBothBlocksOfASearchInWireOrder()
    {
        $call = ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']];
        $found = ['type' => 'tool_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => ['type' => 'tool_search_tool_search_result', 'tool_references' => [['type' => 'tool_reference', 'tool_name' => 'get_weather']]]];

        $normalized = (new AssistantMessageNormalizer())->normalize(new AssistantMessage(
            new ServerToolCall('tool_search', 'srvtoolu_1', 'completed', ['query' => 'weather', 'tools' => ['get_weather']], json_encode([$call, $found])),
            new Text('Looking it up.'),
        ));

        $this->assertSame([
            'role' => 'assistant',
            'content' => [$call, $found, ['type' => 'text', 'text' => 'Looking it up.']],
        ], $normalized);
    }

    #[DataProvider('unreplayablePayloads')]
    public function testNormalizerDropsASearchItCannotReplay(string $name, ?string $replay)
    {
        $normalized = (new AssistantMessageNormalizer())->normalize(new AssistantMessage(
            new ServerToolCall($name, 'srvtoolu_1', 'completed', [], $replay),
            new Text('Looking it up.'),
        ));

        $this->assertSame([['type' => 'text', 'text' => 'Looking it up.']], $normalized['content']);
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function unreplayablePayloads(): iterable
    {
        yield 'no replay payload' => ['tool_search', null];
        yield 'not JSON' => ['tool_search', 'not json'];
        yield 'not a list of blocks' => ['tool_search', '"a string"'];
        yield 'a block of another feature' => ['tool_search', '[{"type":"server_tool_use","name":"web_search"}]'];
        yield 'another server tool' => ['file_search', '[{"type":"server_tool_use","name":"tool_search_tool_bm25"}]'];
    }

    /**
     * @param list<string> $found
     *
     * @return list<array<string, mixed>>
     */
    private function messagesWithSearch(array $found): array
    {
        return [[
            'role' => 'assistant',
            'content' => [
                ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'tool_search_tool_bm25', 'input' => ['query' => 'weather']],
                ['type' => 'tool_search_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => [
                    'type' => 'tool_search_tool_search_result',
                    'tool_references' => array_map(static fn (string $name): array => ['type' => 'tool_reference', 'tool_name' => $name], $found),
                ]],
                ['type' => 'text', 'text' => 'Looking it up.'],
            ],
        ]];
    }

    /**
     * @param list<array<string, mixed>> $tools
     * @param array<string, mixed>       $options
     * @param list<array<string, mixed>> $messages
     *
     * @return array<string, mixed>
     */
    private function sendRequest(string $model, array $tools, array $options = [], array $messages = []): array
    {
        $body = null;
        $client = new ModelClient(new MockHttpClient(static function ($method, $url, $requestOptions) use (&$body) {
            $body = json_decode($requestOptions['body'], true);

            return new JsonMockResponse('{}');
        }), 'key');

        $client->request((new ModelCatalog())->getModel($model), ['messages' => $messages], ['tools' => $tools] + $options);

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(string $name, bool $deferred = false, string $model = 'claude-sonnet-4-5'): array
    {
        $tool = new Tool(new ExecutionReference('Tool'), $name, 'd', deferred: $deferred);

        return (new ToolNormalizer())->normalize($tool, null, [Contract::CONTEXT_MODEL => (new ModelCatalog())->getModel($model)]);
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
     * @return list<ServerToolCallResult>
     */
    private function serverToolCalls(array $events): array
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $calls = [];
        foreach ((new ResultConverter())->convert(new InMemoryRawResult([], $events, $response), ['stream' => true])->getContent() as $delta) {
            if ($delta instanceof ServerToolCallComplete) {
                $calls[] = $delta->getServerToolCall();
            }
        }

        return $calls;
    }
}
