<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\OpenAi;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\EmbeddingsModelClient;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class EmbeddingsModelClientTest extends TestCase
{
    #[TestWith(['test.azure.com', 'https://test.azure.com/openai/deployments/embeddings-deployment/embeddings?api-version=2023-12-01'])]
    #[TestWith(['https://test.azure.com', 'https://test.azure.com/openai/deployments/embeddings-deployment/embeddings?api-version=2023-12-01'])]
    #[TestWith(['https://test.azure.com/', 'https://test.azure.com/openai/deployments/embeddings-deployment/embeddings?api-version=2023-12-01'])]
    #[TestWith(['http://localhost:8080', 'http://localhost:8080/openai/deployments/embeddings-deployment/embeddings?api-version=2023-12-01'])]
    public function testItNormalizesTheBaseUrl(string $baseUrl, string $expectedUrl)
    {
        $resultCallback = function (string $method, string $url) use ($expectedUrl): MockResponse {
            $this->assertSame($expectedUrl, $url);

            return new MockResponse();
        };

        $client = new EmbeddingsModelClient(new MockHttpClient([$resultCallback]), $baseUrl, 'embeddings-deployment', '2023-12-01', 'test-api-key');
        $client->request(new EmbeddingsModel('text-embedding-3-small'), 'Hello, world!');
    }

    public function testItThrowsExceptionWhenDeploymentIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The deployment must not be empty.');

        new EmbeddingsModelClient(new MockHttpClient(), 'test.azure.com', '', 'api-version', 'api-key');
    }

    public function testItThrowsExceptionWhenApiVersionIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API version must not be empty.');

        new EmbeddingsModelClient(new MockHttpClient(), 'test.azure.com', 'deployment', '', 'api-key');
    }

    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new EmbeddingsModelClient(new MockHttpClient(), 'test.azure.com', 'deployment', 'api-version', '');
    }

    public function testItAcceptsValidParameters()
    {
        $client = new EmbeddingsModelClient(new MockHttpClient(), 'test.azure.com', 'text-embedding-ada-002', '2023-12-01-preview', 'valid-api-key');

        $this->assertInstanceOf(EmbeddingsModelClient::class, $client);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $client = new EmbeddingsModelClient(new MockHttpClient(), 'test.azure.com', 'deployment', '2023-12-01', 'api-key');

        $this->assertTrue($client->supports(new EmbeddingsModel('text-embedding-3-small')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://test.azure.com/openai/deployments/embeddings-deployment/embeddings?api-version=2023-12-01', $url);
            self::assertSame(['api-key: test-api-key'], $options['normalized_headers']['api-key']);
            self::assertSame('{"model":"text-embedding-3-small","input":"Hello, world!"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new EmbeddingsModelClient($httpClient, 'test.azure.com', 'embeddings-deployment', '2023-12-01', 'test-api-key');
        $client->request(new EmbeddingsModel('text-embedding-3-small'), 'Hello, world!');
    }

    public function testHttpOptionsAreSentWithTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame(30.0, $options['max_duration']);
            self::assertSame('{"model":"text-embedding-3-small","input":"Hello, world!"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new EmbeddingsModelClient($httpClient, 'test.azure.com', 'embeddings-deployment', '2023-12-01', 'test-api-key');
        $client->request(new EmbeddingsModel('text-embedding-3-small'), 'Hello, world!', ['http_options' => ['max_duration' => 30, 'json' => 'overridden']]);
    }
}
