<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\Jina;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Jina\Reranker;
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
                ['index' => 1, 'relevance_score' => 0.88],
                ['index' => 0, 'relevance_score' => 0.61],
            ],
        ]);

        $client = new MockHttpClient([$mockResponse]);
        $reranker = new Reranker($client, 'test-api-key');

        $doc0 = new VectorDocument('doc-0', new Vector([0.1, 0.2]), new Metadata([Metadata::KEY_TEXT => 'First document']));
        $doc1 = new VectorDocument('doc-1', new Vector([0.3, 0.4]), new Metadata([Metadata::KEY_TEXT => 'Second document']));

        $result = $reranker->rerank('test query', [$doc0, $doc1], 2);

        $this->assertCount(2, $result);
        $this->assertSame('doc-1', $result[0]->getId());
        $this->assertSame(0.88, $result[0]->getScore());
        $this->assertSame('doc-0', $result[1]->getId());
        $this->assertSame(0.61, $result[1]->getScore());
    }

    public function testSendsDocumentsAsObjectsWithTextField()
    {
        $capturedBody = null;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'results' => [['index' => 0, 'relevance_score' => 0.9]],
            ]);
        });

        $reranker = new Reranker($mockClient, 'api-key', 'jina-reranker-v2-base-multilingual');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'My document text']));
        $reranker->rerank('my query', [$doc], 1);

        $this->assertNotNull($capturedBody);
        $this->assertSame('jina-reranker-v2-base-multilingual', $capturedBody['model']);
        $this->assertSame('my query', $capturedBody['query']);
        $this->assertSame([['text' => 'My document text']], $capturedBody['documents']);
        $this->assertSame(1, $capturedBody['top_n']);
    }

    public function testSendsCorrectApiEndpoint()
    {
        $capturedUrl = null;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): JsonMockResponse {
            $capturedUrl = $url;

            return new JsonMockResponse(['results' => [['index' => 0, 'relevance_score' => 0.5]]]);
        });

        $reranker = new Reranker($mockClient, 'api-key');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'Text']));
        $reranker->rerank('query', [$doc], 1);

        $this->assertStringContainsString('jina.ai', $capturedUrl);
        $this->assertStringContainsString('rerank', $capturedUrl);
    }

    public function testReturnsEmptyForEmptyDocuments()
    {
        $client = new MockHttpClient();
        $reranker = new Reranker($client, 'api-key');

        $result = $reranker->rerank('query', [], 5);

        $this->assertSame([], $result);
        $this->assertSame(0, $client->getRequestsCount());
    }
}
