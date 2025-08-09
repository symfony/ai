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
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\Albert\EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(EmbeddingsModelClient::class)]
#[Small]
final class EmbeddingsModelClientTest extends TestCase
{
    public function testConstructorThrowsExceptionForEmptyApiKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new EmbeddingsModelClient(
            new MockHttpClient(),
            '',
            'https://albert.example.com/'
        );
    }

    public function testConstructorThrowsExceptionForEmptyBaseUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base URL must not be empty.');

        new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            ''
        );
    }

    public function testSupportsEmbeddingsModel()
    {
        $client = new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $embeddingsModel = new Embeddings('text-embedding-ada-002');
        $this->assertTrue($client->supports($embeddingsModel, Action::CALCULATE_EMBEDDINGS));
    }

    public function testDoesNotSupportNonEmbeddingsModel()
    {
        $client = new EmbeddingsModelClient(
            new MockHttpClient(),
            'test-api-key',
            'https://albert.example.com/'
        );

        $gptModel = new Gpt('gpt-3.5-turbo');
        $this->assertFalse($client->supports($gptModel, Action::CALCULATE_EMBEDDINGS));
    }

    #[DataProvider('providePayloadToJson')]
    public function testRequestSendsCorrectHttpRequest(array|string $payload, array $options, array|string $expectedJson)
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
        $result = $client->request($model, Action::CALCULATE_EMBEDDINGS, $payload, $options);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('POST', $capturedRequest['method']);
        $this->assertSame('https://albert.example.com/v1/embeddings', $capturedRequest['url']);
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

    public function testRequestHandlesBaseUrlWithoutTrailingSlash()
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
        $client->request($model, Action::CALCULATE_EMBEDDINGS, ['input' => 'test']);

        $this->assertSame('https://albert.example.com/v1/embeddings', $capturedUrl);
    }

    public function testRequestHandlesBaseUrlWithTrailingSlash()
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
        $client->request($model, Action::CALCULATE_EMBEDDINGS, ['input' => 'test']);

        $this->assertSame('https://albert.example.com/v1/embeddings', $capturedUrl);
    }
}
