<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Rerank;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Rerank\ModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\RerankModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsRerankModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new RerankModel('cohere/rerank-v3.5')));
    }

    public function testItDoesNotSupportNonRerankModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new CompletionsModel('openrouter/auto')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://openrouter.ai/api/v1/rerank', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertContains('Content-Type: application/json', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertSame('cohere/rerank-v3.5', $body['model']);
            $this->assertSame('What is AI?', $body['query']);
            $this->assertSame(['Document about AI', 'Document about cooking'], $body['documents']);
            $this->assertArrayNotHasKey('top_n', $body);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new RerankModel('cohere/rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Document about AI', 'Document about cooking'],
        ]);
    }

    public function testItSendsTopNOption()
    {
        $httpClient = new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame(3, $body['top_n']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new RerankModel('cohere/rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Doc 1', 'Doc 2', 'Doc 3', 'Doc 4'],
        ], ['top_n' => 3]);
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new RerankModel('cohere/rerank-v3.5'), 'invalid string payload');
    }

    public function testItThrowsExceptionForMissingQueryKey()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new RerankModel('cohere/rerank-v3.5'), ['texts' => ['doc1']]);
    }

    public function testItThrowsExceptionForMissingTextsKey()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reranker payload must be an array with "query" and "texts" keys.');

        $client->request(new RerankModel('cohere/rerank-v3.5'), ['query' => 'test']);
    }
}
