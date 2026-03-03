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
        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.cohere.com/v2/embed', $url);

            $body = json_decode($options['body'], true);
            self::assertSame('embed-english-v3.0', $body['model']);
            self::assertSame(['Hello, world!'], $body['texts']);
            self::assertSame('search_document', $body['input_type']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Embeddings('embed-english-v3.0'), 'Hello, world!');
    }

    public function testItUsesInputTypeFromOptions()
    {
        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertSame('search_query', $body['input_type']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Embeddings('embed-english-v3.0'), 'Hello, world!', [
            'input_type' => 'search_query',
        ]);
    }

    public function testItUsesInputTypeFromModelOptions()
    {
        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertSame('classification', $body['input_type']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test-key');

        $model = new Embeddings('embed-english-v3.0', [], ['input_type' => Embeddings::INPUT_TYPE_CLASSIFICATION]);
        $client->request($model, 'Hello, world!');
    }
}
