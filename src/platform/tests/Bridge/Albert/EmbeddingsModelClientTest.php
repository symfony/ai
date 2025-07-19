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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Albert\EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(EmbeddingsModelClient::class)]
#[Small]
final class EmbeddingsModelClientTest extends TestCase
{
    #[Test]
    public function constructorThrowsExceptionForEmptyApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new EmbeddingsModelClient(
            new MockHttpClient(),
            '',
            'https://albert.example.com/'
        );
    }

    #[Test]
    public function constructorThrowsExceptionForEmptyBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base URL must not be empty.');

        new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            ''
        );
    }

    #[Test]
    public function supportsEmbeddingsModel(): void
    {
        $client = new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $embeddingsModel = new Embeddings('text-embedding-ada-002');
        self::assertTrue($client->supports($embeddingsModel));
    }

    #[Test]
    public function doesNotSupportNonEmbeddingsModel(): void
    {
        $client = new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $gptModel = new GPT('gpt-3.5-turbo');
        self::assertFalse($client->supports($gptModel));
    }

    #[Test]
    #[DataProvider('providePayloadToJson')]
    public function requestSendsCorrectHttpRequest(array|string $payload, array $options, array|string $expectedJson): void
    {
        $capturedRequest = null;
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedRequest) {
            $capturedRequest = ['method' => $method, 'url' => $url, 'options' => $options];

            return new JsonMockResponse(['data' => []]);
        });

        $client = new EmbeddingsModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Embeddings('text-embedding-ada-002');
        $result = $client->request($model, $payload, $options);

        self::assertNotNull($capturedRequest);
        self::assertSame('POST', $capturedRequest['method']);
        self::assertSame('https://albert.example.com/v1/embeddings', $capturedRequest['url']);
        self::assertArrayHasKey('normalized_headers', $capturedRequest['options']);
        self::assertArrayHasKey('authorization', $capturedRequest['options']['normalized_headers']);
        self::assertStringContainsString('Bearer test-api-key', (string) $capturedRequest['options']['normalized_headers']['authorization'][0]);

        // Check JSON body - it might be in 'body' after processing
        if (isset($capturedRequest['options']['body'])) {
            $actualJson = json_decode($capturedRequest['options']['body'], true);
            self::assertEquals($expectedJson, $actualJson);
        } else {
            self::assertSame($expectedJson, $capturedRequest['options']['json']);
        }
    }

    public static function providePayloadToJson(): iterable
    {
        yield 'with array payload and no options' => [
            ['input' => 'test text', 'model' => 'text-embedding-ada-002'],
            [],
            ['input' => 'test text', 'model' => 'text-embedding-ada-002'],
        ];

        yield 'with string payload and no options' => [
            'test text',
            [],
            'test text',
        ];

        yield 'with array payload and options' => [
            ['input' => 'test text', 'model' => 'text-embedding-ada-002'],
            ['dimensions' => 1536],
            ['dimensions' => 1536, 'input' => 'test text', 'model' => 'text-embedding-ada-002'],
        ];

        yield 'options override payload values' => [
            ['input' => 'test text', 'model' => 'text-embedding-ada-002'],
            ['model' => 'text-embedding-3-small'],
            ['model' => 'text-embedding-3-small', 'input' => 'test text'],
        ];
    }

    #[Test]
    public function requestHandlesBaseUrlWithoutTrailingSlash(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse(['data' => []]);
        });

        $client = new EmbeddingsModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Embeddings('text-embedding-ada-002');
        $client->request($model, ['input' => 'test']);

        self::assertSame('https://albert.example.com/v1/embeddings', $capturedUrl);
    }

    #[Test]
    public function requestHandlesBaseUrlWithTrailingSlash(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse(['data' => []]);
        });

        $client = new EmbeddingsModelClient(
            $httpClient,
            'test-api-key',
            'https://albert.example.com/v1'
        );

        $model = new Embeddings('text-embedding-ada-002');
        $client->request($model, ['input' => 'test']);

        self::assertSame('https://albert.example.com/v1/embeddings', $capturedUrl);
    }
}
