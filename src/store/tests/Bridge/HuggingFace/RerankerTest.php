<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Bridge\HuggingFace;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\HuggingFace\Reranker;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class RerankerTest extends TestCase
{
    public function testReranksDocumentsInCorrectOrder()
    {
        // TEI returns results sorted by score descending
        $mockResponse = new JsonMockResponse([
            ['index' => 1, 'score' => 0.91, 'text' => 'Second document'],
            ['index' => 0, 'score' => 0.55, 'text' => 'First document'],
        ]);

        $client = new MockHttpClient([$mockResponse]);
        $reranker = new Reranker($client, 'http://localhost:8080');

        $doc0 = new VectorDocument('doc-0', new Vector([0.1, 0.2]), new Metadata([Metadata::KEY_TEXT => 'First document']));
        $doc1 = new VectorDocument('doc-1', new Vector([0.3, 0.4]), new Metadata([Metadata::KEY_TEXT => 'Second document']));

        $result = $reranker->rerank('test query', [$doc0, $doc1], 2);

        $this->assertCount(2, $result);
        $this->assertSame('doc-1', $result[0]->getId());
        $this->assertSame(0.91, $result[0]->getScore());
        $this->assertSame('doc-0', $result[1]->getId());
        $this->assertSame(0.55, $result[1]->getScore());
    }

    public function testSendsTextStringsNotObjects()
    {
        $capturedBody = null;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                ['index' => 0, 'score' => 0.9, 'text' => 'Document text'],
            ]);
        });

        $reranker = new Reranker($mockClient, 'http://my-tei-server:8080');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'Document text']));
        $reranker->rerank('my query', [$doc], 1);

        $this->assertNotNull($capturedBody);
        $this->assertSame('my query', $capturedBody['query']);
        $this->assertSame(['Document text'], $capturedBody['texts']);
        $this->assertFalse($capturedBody['raw_scores']);
        $this->assertTrue($capturedBody['truncate']);
    }

    public function testUsesCorrectEndpointUrl()
    {
        $capturedUrl = null;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): JsonMockResponse {
            $capturedUrl = $url;

            return new JsonMockResponse([
                ['index' => 0, 'score' => 0.5, 'text' => 'Text'],
            ]);
        });

        $reranker = new Reranker($mockClient, 'http://localhost:8080');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'Text']));
        $reranker->rerank('query', [$doc], 1);

        $this->assertSame('http://localhost:8080/rerank', $capturedUrl);
    }

    public function testTrimsTrailingSlashFromEndpoint()
    {
        $capturedUrl = null;
        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): JsonMockResponse {
            $capturedUrl = $url;

            return new JsonMockResponse([
                ['index' => 0, 'score' => 0.5, 'text' => 'Text'],
            ]);
        });

        $reranker = new Reranker($mockClient, 'http://localhost:8080/');

        $doc = new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'Text']));
        $reranker->rerank('query', [$doc], 1);

        $this->assertSame('http://localhost:8080/rerank', $capturedUrl);
    }

    public function testReturnsEmptyForEmptyDocuments()
    {
        $client = new MockHttpClient();
        $reranker = new Reranker($client, 'http://localhost:8080');

        $result = $reranker->rerank('query', [], 5);

        $this->assertSame([], $result);
        $this->assertSame(0, $client->getRequestsCount());
    }

    public function testTopKLimitsResults()
    {
        $mockResponse = new JsonMockResponse([
            ['index' => 0, 'score' => 0.9, 'text' => 'First'],
            ['index' => 1, 'score' => 0.8, 'text' => 'Second'],
            ['index' => 2, 'score' => 0.7, 'text' => 'Third'],
        ]);

        $client = new MockHttpClient([$mockResponse]);
        $reranker = new Reranker($client, 'http://localhost:8080');

        $docs = [
            new VectorDocument('doc-0', new Vector([0.1]), new Metadata([Metadata::KEY_TEXT => 'First'])),
            new VectorDocument('doc-1', new Vector([0.2]), new Metadata([Metadata::KEY_TEXT => 'Second'])),
            new VectorDocument('doc-2', new Vector([0.3]), new Metadata([Metadata::KEY_TEXT => 'Third'])),
        ];

        $result = $reranker->rerank('query', $docs, 2);

        $this->assertCount(2, $result);
    }
}
