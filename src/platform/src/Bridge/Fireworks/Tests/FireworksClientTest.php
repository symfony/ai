<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Bridge\Fireworks\FireworksClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FireworksClientTest extends TestCase
{
    public function testSupportsFireworksModel()
    {
        $client = new FireworksClient(new MockHttpClient(), 'test-api-key');

        $this->assertTrue($client->supports(new Fireworks('accounts/fireworks/models/kimi-k2p6')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = new FireworksClient(new MockHttpClient(), 'test-api-key');

        $this->assertFalse($client->supports(new Model('gpt-4')));
    }

    public function testRequestSendsChatCompletionsToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/chat/completions', $url);
            self::assertArrayHasKey('normalized_headers', $options);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            self::assertArrayHasKey('messages', $body);
            self::assertSame('accounts/fireworks/models/kimi-k2p6', $body['model']);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $client = new FireworksClient($httpClient, 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);

        $client->request($model, ['messages' => [['role' => 'user', 'content' => 'Hi']], 'model' => 'accounts/fireworks/models/kimi-k2p6']);
        $this->assertTrue($requestMade);
    }

    public function testRequestMergesOptionsWithPayload()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            $body = json_decode($options['body'], true);
            self::assertArrayHasKey('messages', $body);
            self::assertArrayHasKey('temperature', $body);
            self::assertSame(0.7, $body['temperature']);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $client = new FireworksClient($httpClient, 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);

        $client->request(
            $model,
            ['messages' => [['role' => 'user', 'content' => 'Hi']], 'model' => 'accounts/fireworks/models/kimi-k2p6'],
            ['temperature' => 0.7],
        );
        $this->assertTrue($requestMade);
    }

    public function testRequestSendsEmbeddingsToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/embeddings', $url);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            self::assertSame('accounts/fireworks/models/qwen3-embedding-8b', $body['model']);
            self::assertSame('text to embed', $body['input']);

            return new JsonMockResponse(['data' => [['embedding' => [0.1, 0.2]]]]);
        });

        $client = new FireworksClient($httpClient, 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/qwen3-embedding-8b', [Capability::INPUT_TEXT, Capability::EMBEDDINGS]);

        $client->request($model, 'text to embed');
        $this->assertTrue($requestMade);
    }

    public function testRequestSendsRerankToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/rerank', $url);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            self::assertSame('accounts/fireworks/models/qwen3-reranker-8b', $body['model']);
            self::assertSame('What is AI?', $body['query']);
            self::assertSame(['AI is a field of study', 'Cooking is fun'], $body['documents']);

            return new JsonMockResponse(['data' => [['index' => 0, 'relevance_score' => 0.9]]]);
        });

        $client = new FireworksClient($httpClient, 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING]);

        $client->request($model, ['query' => 'What is AI?', 'documents' => ['AI is a field of study', 'Cooking is fun']]);
        $this->assertTrue($requestMade);
    }

    public function testRequestRerankThrowsOnStringPayload()
    {
        $client = new FireworksClient(new MockHttpClient(), 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rerank payload must be an array with "query" and "documents" keys.');

        $client->request($model, 'invalid string payload');
    }

    public function testRequestRerankThrowsOnMissingDocuments()
    {
        $client = new FireworksClient(new MockHttpClient(), 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/qwen3-reranker-8b', [Capability::RERANKING]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rerank payload must be an array with "query" and "documents" keys.');

        $client->request($model, ['query' => 'What is AI?']);
    }

    public function testRequestSendsTextToImageToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.fireworks.ai/inference/v1/workflows/accounts/fireworks/models/flux-1-schnell-fp8/text_to_image', $url);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('Accept: application/json', $options['normalized_headers']['accept'][0]);

            $body = json_decode($options['body'], true);
            self::assertSame('a cat in space', $body['prompt']);

            return new JsonMockResponse(['base64' => ['data:image/png;base64,iVBORw0KGgo=']]);
        });

        $client = new FireworksClient($httpClient, 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/flux-1-schnell-fp8', [Capability::INPUT_TEXT, Capability::TEXT_TO_IMAGE]);

        $client->request($model, 'a cat in space');
        $this->assertTrue($requestMade);
    }

    public function testRequestThrowsOnUnsupportedCapability()
    {
        $client = new FireworksClient(new MockHttpClient(), 'test-api-key');
        $model = new Fireworks('accounts/fireworks/models/unknown-model');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported model');

        $client->request($model, ['messages' => [['role' => 'user', 'content' => 'Hi']]]);
    }
}
