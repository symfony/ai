<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests\Reranker;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Reranker;
use Symfony\AI\Platform\Bridge\Cohere\Reranker\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsRerankerModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Reranker('rerank-v3.5')));
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
            $this->assertSame('https://api.cohere.com/v2/rerank', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('rerank-v3.5', $body['model']);
            $this->assertSame('What is AI?', $body['query']);
            $this->assertSame(['Document about AI', 'Document about cooking'], $body['documents']);
            $this->assertArrayNotHasKey('top_n', $body);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Document about AI', 'Document about cooking'],
        ]);
    }

    public function testItSendsTopNOption()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame(3, $body['top_n']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Doc 1', 'Doc 2', 'Doc 3', 'Doc 4'],
        ], ['top_n' => 3]);
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-v3.5'), 'invalid string payload');
    }

    public function testItThrowsExceptionForMissingQueryKey()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-v3.5'), ['texts' => ['doc1']]);
    }

    public function testItThrowsExceptionForMissingTextsKey()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new Reranker('rerank-v3.5'), ['query' => 'test']);
    }
}
