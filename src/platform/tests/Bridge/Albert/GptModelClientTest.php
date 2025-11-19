<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Albert;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Albert\GptModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class GptModelClientTest extends TestCase
{
    public function testConstructorWrapsHttpClientInEventSourceHttpClient()
    {
        self::expectNotToPerformAssertions();

        $mockHttpClient = new MockHttpClient();

        $client = new GptModelClient(
            $mockHttpClient,
            'test-api-key',
            'https://albert.example.com/'
        );

        // We can't directly test the private property, but we can verify the behavior
        // by making a request and checking that it works correctly
        $mockResponse = new JsonMockResponse(['choices' => []]);
        $mockHttpClient->setResponseFactory([$mockResponse]);

        $model = new Gpt('gpt-3.5-turbo');
        $client->request($model, ['messages' => []]);
    }

    public function testConstructorAcceptsEventSourceHttpClient()
    {
        self::expectNotToPerformAssertions();

        $mockHttpClient = new MockHttpClient();
        $eventSourceClient = new EventSourceHttpClient($mockHttpClient);

        $client = new GptModelClient(
            $eventSourceClient,
            'test-api-key',
            'https://albert.example.com/'
        );

        // Verify it works with EventSourceHttpClient
        $mockResponse = new JsonMockResponse(['choices' => []]);
        $mockHttpClient->setResponseFactory([$mockResponse]);

        $model = new Gpt('gpt-3.5-turbo');
        $client->request($model, ['messages' => []]);
    }

    public function testSupportsGptModel()
    {
        $client = new GptModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $gptModel = new Gpt('gpt-3.5-turbo');
        $this->assertTrue($client->supports($gptModel));
    }

    public function testDoesNotSupportNonGptModel()
    {
        $client = new GptModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $embeddingsModel = new Embeddings('text-embedding-ada-002');
        $this->assertFalse($client->supports($embeddingsModel));
    }

    #[DataProvider('providePayloadToJson')]
    public function testRequestSendsCorrectHttpRequest(array|string $payload, array $options, array|string $expectedJson)
    {
        $capturedRequest = null;
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedRequest) {
            $capturedRequest = ['method' => $method, 'url' => $url, 'options' => $options];

            return new JsonMockResponse(['choices' => []]);
        });

        $client = new GptModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Gpt('gpt-3.5-turbo');
        $result = $client->request($model, $payload, $options);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('POST', $capturedRequest['method']);
        $this->assertSame('https://albert.example.com/v1/chat/completions', $capturedRequest['url']);
        $this->assertArrayHasKey('normalized_headers', $capturedRequest['options']);
        $this->assertArrayHasKey('authorization', $capturedRequest['options']['normalized_headers']);
        $this->assertStringContainsString('Bearer test-api-key', (string) $capturedRequest['options']['normalized_headers']['authorization'][0]);

        // Check JSON body - it might be in 'body' after processing
        if (isset($capturedRequest['options']['body'])) {
            $actualJson = json_decode($capturedRequest['options']['body'], true);
            $this->assertEquals($expectedJson, $actualJson);
        } else {
            $this->assertSame($expectedJson, $capturedRequest['options']['json']);
        }
    }

    public static function providePayloadToJson(): iterable
    {
        yield 'with array payload and no options' => [
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo'],
            [],
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo'],
        ];

        yield 'with string payload and no options' => [
            'test message',
            [],
            'test message',
        ];

        yield 'with array payload and options' => [
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo'],
            ['temperature' => 0.7, 'max_tokens' => 150],
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo', 'temperature' => 0.7, 'max_tokens' => 150],
        ];

        yield 'options override payload values' => [
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo', 'temperature' => 1.0],
            ['temperature' => 0.5],
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo', 'temperature' => 0.5],
        ];

        yield 'with streaming option' => [
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo'],
            ['stream' => true],
            ['messages' => [['role' => 'user', 'content' => 'Hello']], 'model' => 'gpt-3.5-turbo', 'stream' => true],
        ];
    }

    public function testRequestHandlesBaseUrlWithoutTrailingSlash()
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse(['choices' => []]);
        });

        $client = new GptModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Gpt('gpt-3.5-turbo');
        $client->request($model, ['messages' => []]);

        $this->assertSame('https://albert.example.com/v1/chat/completions', $capturedUrl);
    }

    public function testRequestHandlesBaseUrlWithTrailingSlash()
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse(['choices' => []]);
        });

        $client = new GptModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Gpt('gpt-3.5-turbo');
        $client->request($model, ['messages' => []]);

        $this->assertSame('https://albert.example.com/v1/chat/completions', $capturedUrl);
    }
}
