<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings\ModelClient;
use Symfony\AI\Platform\Bridge\Cohere\InputType;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsEmbeddingsModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Embeddings('embed-english-v3.0')));
    }

    public function testItDoesNotSupportCohereModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Cohere('command-a-03-2025')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cohere.com/v2/embed', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('embed-english-v3.0', $body['model']);
            $this->assertSame(['Hello, world!'], $body['texts']);
            $this->assertSame('search_document', $body['input_type']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Embeddings('embed-english-v3.0'), 'Hello, world!');
    }

    public function testItUsesInputTypeFromOptions()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('search_query', $body['input_type']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Embeddings('embed-english-v3.0'), 'Hello, world!', [
            'input_type' => InputType::SearchQuery,
        ]);
    }

    public function testItUsesInputTypeFromModelOptions()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('classification', $body['input_type']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $model = new Embeddings('embed-english-v3.0', [], ['input_type' => InputType::Classification]);
        $client->request($model, 'Hello, world!');
    }
}
