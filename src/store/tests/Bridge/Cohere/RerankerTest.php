<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\Cohere;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Cohere\Reranker;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class RerankerTest extends TestCase
{
    public function testReranksDocumentsInCorrectOrder()
    {
        $mockResponse = new JsonMockResponse([
            'results' => [
                ['index' => 1, 'relevance_score' => 0.95],
                ['index' => 0, 'relevance_score' => 0.72],
            ],
        ]);

        $client = new MockHttpClient([$mockResponse]);
        $reranker = new Reranker($client, 'test-api-key');

        $doc0 = new VectorDocument('doc-0', new Vector([0.1, 0.2]), new Metadata([Metadata::KEY_TEXT => 'First document']));
        $doc1 = new VectorDocument('doc-1', new Vector([0.3, 0.4]), new Metadata([Metadata::KEY_TEXT => 'Second document']));

        $result = $reranker->rerank('test query', [$doc0, $doc1], 2);

        $this->assertCount(2, $result);
        $this->assertSame('doc-1', $result[0]->getId());
        $this->assertSame(0.95, $result[0]->getScore());
        $this->assertSame('doc-0', $result[1]->getId());
        $this->assertSame(0.72, $result[1]->getScore());
    }

    public function testSendsCorrectHttpRequest()
    {
        $capturedRequest = null;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedRequest): JsonMockResponse {
            $capturedRequest = ['method' => $method, 'url' => $url, 'options' => $options];

            return new JsonMockResponse([
                'results' => [
                    ['index' => 0, 'relevance_score' => 0.9],
                ],
            ]);
        });

        $reranker = new Reranker($mockClient, 'my-api-key', 'rerank-v3.5');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'Some text']));
        $reranker->rerank('my query', [$doc], 1);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('POST', $capturedRequest['method']);
        $this->assertStringContainsString('cohere.com', $capturedRequest['url']);

        $body = json_decode($capturedRequest['options']['body'], true);
        $this->assertSame('rerank-v3.5', $body['model']);
        $this->assertSame('my query', $body['query']);
        $this->assertSame(['Some text'], $body['documents']);
        $this->assertSame(1, $body['top_n']);
    }

    public function testSendsAuthorizationHeader()
    {
        $capturedHeaders = null;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedHeaders): JsonMockResponse {
            $capturedHeaders = $options['normalized_headers'] ?? $options['headers'] ?? [];

            return new JsonMockResponse(['results' => [['index' => 0, 'relevance_score' => 0.5]]]);
        });

        $reranker = new Reranker($mockClient, 'secret-key');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'Text']));
        $reranker->rerank('query', [$doc], 1);

        $this->assertNotNull($capturedHeaders);
    }

    public function testReturnsEmptyForEmptyDocuments()
    {
        $client = new MockHttpClient();
        $reranker = new Reranker($client, 'api-key');

        $result = $reranker->rerank('query', [], 5);

        $this->assertSame([], $result);
        $this->assertSame(0, $client->getRequestsCount());
    }

    public function testTopKLimitsResults()
    {
        $mockResponse = new JsonMockResponse([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.9],
                ['index' => 1, 'relevance_score' => 0.8],
            ],
        ]);

        $client = new MockHttpClient([$mockResponse]);
        $reranker = new Reranker($client, 'api-key');

        $docs = [
            new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'First'])),
            new VectorDocument('doc-1', new Vector([0.2]), new Metadata([Metadata::KEY_TEXT => 'Second'])),
            new VectorDocument('doc-2', new Vector([0.3]), new Metadata([Metadata::KEY_TEXT => 'Third'])),
        ];

        $result = $reranker->rerank('query', $docs, 2);

        $body = [];
        $this->assertCount(2, $result);
    }
}
