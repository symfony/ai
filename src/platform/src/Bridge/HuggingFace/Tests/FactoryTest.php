<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelApiCatalog;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelCatalog;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\Bridge\HuggingFace\Reranker;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\Replay\CassetteHttpClient;
use Symfony\AI\Platform\Test\Replay\HttpCassette;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FactoryTest extends TestCase
{
    #[TestDox('Creates Platform with default provider and auto-generated components')]
    public function testCreateWithDefaults()
    {
        $platform = Factory::createPlatform('test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestDox('Handles EventSourceHttpClient correctly')]
    public function testCreateWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('test-api-key', Provider::NSCALE, $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[TestWith([null, 'test/model'])]
    #[TestWith([Provider::TOGETHER, 'test/model:together'])]
    public function testToolCallingRoundTrip(?string $provider, string $expectedModel)
    {
        $toolCall = ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Paris"}']];
        $responses = [
            new JsonMockResponse(['choices' => [['message' => ['content' => null, 'tool_calls' => [$toolCall]], 'finish_reason' => 'tool_calls']]]),
            new JsonMockResponse(['choices' => [['message' => ['content' => 'It is sunny in Paris.'], 'finish_reason' => 'stop']]]),
        ];
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $responses): JsonMockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR)];

            return $responses[\count($requests) - 1];
        });
        $platform = Factory::createPlatform('test-api-key', $provider, $httpClient, self::createModelCatalog());

        $messages = new MessageBag(Message::ofUser('What is the weather in Paris?'));
        $options = [
            'tools' => [new Tool(new ExecutionReference(self::class), 'weather', 'Get the weather', [
                'type' => 'object',
                'properties' => ['city' => ['type' => 'string', 'description' => 'The city to look up']],
                'required' => ['city'],
                'additionalProperties' => false,
            ])],
        ];
        $result = $platform->invoke('test/model', $messages, $options)->getResult();

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertSame(['city' => 'Paris'], $result->getContent()[0]->getArguments());

        $messages = $messages->with(Message::ofAssistant($result));
        $messages = $messages->with(Message::ofToolCall($result->getContent()[0], 'sunny'));

        $this->assertSame('It is sunny in Paris.', $platform->invoke('test/model', $messages, $options)->asText());
        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            $this->assertSame('POST', $request['method']);
            $this->assertSame('https://router.huggingface.co/v1/chat/completions', $request['url']);
            $this->assertSame($expectedModel, $request['body']['model']);
            $this->assertSame('auto', $request['body']['tool_choice']);
            $this->assertSame('weather', $request['body']['tools'][0]['function']['name']);
            $this->assertSame(['city' => ['type' => 'string', 'description' => 'The city to look up']], $request['body']['tools'][0]['function']['parameters']['properties']);
        }
        $this->assertSame([
            ['role' => 'user', 'content' => 'What is the weather in Paris?'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [$toolCall]],
            ['role' => 'tool', 'content' => 'sunny', 'tool_call_id' => 'call_1'],
        ], $requests[1]['body']['messages']);
    }

    public function testStreamsChatCompletion()
    {
        $chunks = [
            ['choices' => [['delta' => ['role' => 'assistant', 'content' => 'Hello'], 'finish_reason' => null]]],
            ['choices' => [['delta' => ['content' => ' world'], 'finish_reason' => 'stop']]],
        ];
        $body = implode('', array_map(static fn (array $chunk): string => 'data: '.json_encode($chunk)."\n\n", $chunks))."data: [DONE]\n\n";
        $httpClient = new MockHttpClient(new MockResponse($body, ['response_headers' => ['content-type' => 'text/event-stream']]));
        $platform = Factory::createPlatform('test-api-key', httpClient: $httpClient, modelCatalog: self::createModelCatalog());

        $text = '';
        foreach ($platform->invoke('test/model', new MessageBag(Message::ofUser('Hi')), ['stream' => true])->asTextStream() as $delta) {
            $text .= $delta;
        }

        $this->assertSame('Hello world', $text);
    }

    public function testCreatesEmbeddings()
    {
        $response = new JsonMockResponse([[0.1, 0.2], [0.3, 0.4]]);
        $platform = Factory::createPlatform('test-api-key', Provider::NSCALE, new MockHttpClient($response), self::createModelCatalog());

        $result = $platform->invoke('test/embeddings', ['Hello', 'World'])->getResult();

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertSame('https://router.huggingface.co/hf-inference/models/test/embeddings/pipeline/feature-extraction', $response->getRequestUrl());
    }

    public function testReranksTexts()
    {
        $response = new JsonMockResponse([['score' => 9.18, 'index' => 1], ['score' => -11.34, 'index' => 0]]);
        $platform = Factory::createPlatform('test-api-key', httpClient: new MockHttpClient($response), modelCatalog: self::createModelCatalog());

        $result = $platform->invoke('test/reranker', ['query' => 'What is PHP?', 'texts' => ['Bananas are yellow', 'PHP is a language']])->getResult();

        $this->assertInstanceOf(RerankingResult::class, $result);
        $this->assertSame(1, $result->getContent()[0]->getIndex());
        $this->assertSame('{"inputs":{"query":"What is PHP?","texts":["Bananas are yellow","PHP is a language"]}}', $response->getRequestOptions()['body']);
    }

    public function testReplayToolCall()
    {
        $httpClient = new CassetteHttpClient(new HttpCassette(__DIR__.'/Fixtures/cassettes/tool_call.json'), record: false);
        $platform = Factory::createPlatform('test-api-key', Provider::CEREBRAS, $httpClient, self::createModelCatalog());

        $calls = $platform->invoke('openai/gpt-oss-120b', new MessageBag(Message::ofUser('What is the current date and time?')))->asToolCalls();

        $this->assertCount(1, $calls);
        $this->assertSame('call_clock', $calls[0]->getId());
        $this->assertSame('clock', $calls[0]->getName());
        $this->assertSame([], $calls[0]->getArguments());
    }

    public function testCreateModelCatalogListsModelsWithApiKey()
    {
        $requestedUrls = [];
        $headers = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrls, &$headers): ResponseInterface {
            $requestedUrls[] = $url;
            $headers = $options['normalized_headers'] ?? [];

            return new JsonMockResponse([]);
        });

        $catalog = Factory::createModelCatalog('secret', Provider::NSCALE, $httpClient);

        $this->assertInstanceOf(ModelApiCatalog::class, $catalog);
        $this->assertSame([], $catalog->getModels());
        $this->assertNotSame([], $requestedUrls);
        $this->assertStringStartsWith('https://huggingface.co/api/models?', $requestedUrls[0]);
        $this->assertStringContainsString('inference_provider=nscale', $requestedUrls[0]);
        $this->assertSame(['Authorization: Bearer secret'], $headers['authorization'] ?? []);
    }

    private static function createModelCatalog(): ModelCatalog
    {
        $chat = ['class' => Completions::class, 'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        return new ModelCatalog([
            'test/model' => $chat,
            'openai/gpt-oss-120b' => $chat,
            'test/embeddings' => ['class' => Embeddings::class, 'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS]],
            'test/reranker' => ['class' => Reranker::class, 'capabilities' => [Capability::INPUT_MULTIPLE, Capability::RERANKING]],
        ]);
    }
}
