<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Supabase\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Supabase\Store as SupabaseStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

class StoreTest extends TestCase
{
    public function testAddThrowsExceptionOnHttpError()
    {
        $httpClient = new MockHttpClient(new MockResponse('Error message', ['http_code' => 400]));
        $store = $this->createStore($httpClient);
        $doc = new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2]), new Metadata([]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Supabase insert failed: Error message');
        $store->add($doc);
    }

    public function testAddEmptyDocumentsDoesNothing()
    {
        $httpClient = new MockHttpClient();
        $store = $this->createStore($httpClient);

        $store->add([]);

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testAddSingleDocument()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 201]));
        $store = $this->createStore($httpClient, 3);
        $doc = new VectorDocument(
            Uuid::v4(),
            new Vector([0.1, 0.2, 0.3]),
            new Metadata(['foo' => 'bar'])
        );

        $store->add($doc);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testAddMultipleDocuments()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 201]));
        $store = $this->createStore($httpClient);

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2]), new Metadata(['a' => '1'])),
            new VectorDocument(Uuid::v4(), new Vector([0.3, 0.4]), new Metadata(['b' => '2'])),
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testAddSkipsDocumentsWithWrongDimension()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 201]));
        $store = $this->createStore($httpClient);

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2]), new Metadata(['valid' => true])),
            new VectorDocument(Uuid::v4(), new Vector([0.1]), new Metadata(['invalid' => true])),
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testQueryThrowsExceptionOnHttpError()
    {
        $httpClient = new MockHttpClient(new MockResponse('Query failed', ['http_code' => 500]));
        $store = $this->createStore($httpClient);
        $queryVector = new Vector([1.0, 2.0]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Supabase query failed: Query failed');
        iterator_to_array($store->query(new VectorQuery($queryVector)));
    }

    public function testQueryWithDefaultOptions()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([]));
        $store = $this->createStore($httpClient);
        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 2.0]))));

        $this->assertSame([], $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testQueryHandlesLimitOption()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([]));
        $store = $this->createStore($httpClient);
        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 2.0])), ['limit' => 1]));

        $this->assertSame([], $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testQueryThrowsExceptionForWrongVectorDimension()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([]));
        $store = $this->createStore($httpClient);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vector dimension mismatch: expected 2');
        iterator_to_array($store->query(new VectorQuery(new Vector([1.0]))));
    }

    public function testQuerySuccess()
    {
        $uuid = Uuid::v4()->toString();
        $expectedResponse = [
            [
                'id' => $uuid,
                'embedding' => '[0.5, 0.6, 0.7]',
                'metadata' => '{"category": "test"}',
                'score' => 0.85,
            ],
        ];
        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));
        $store = $this->createStore($httpClient, 3);
        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 2.0, 3.0])), ['max_items' => 5, 'min_score' => 0.7]));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(VectorDocument::class, $result[0]);
        $this->assertSame($uuid, $result[0]->getId());
        $this->assertSame([0.5, 0.6, 0.7], $result[0]->getVector()->getData());
        $this->assertSame(['category' => 'test'], $result[0]->getMetadata()->getArrayCopy());
        $this->assertSame(0.85, $result[0]->getScore());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testQueryHandlesMultipleResultsAndMultipleOptions()
    {
        $uuid1 = Uuid::v4()->toString();
        $uuid2 = Uuid::v4()->toString();
        $expectedResponse = [
            [
                'id' => $uuid1,
                'embedding' => '[0.1, 0.2]',
                'metadata' => '{"type": "first"}',
                'score' => 0.95,
            ],
            [
                'id' => $uuid2,
                'embedding' => '[0.3, 0.4]',
                'metadata' => '{"type": "second"}',
                'score' => 0.85,
            ],
        ];
        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));
        $store = $this->createStore($httpClient, 2);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 2.0])), ['max_items' => 2, 'min_score' => 0.8]));

        $this->assertCount(2, $result);
        $this->assertInstanceOf(VectorDocument::class, $result[0]);
        $this->assertSame($uuid1, $result[0]->getId());
        $this->assertSame([0.1, 0.2], $result[0]->getVector()->getData());
        $this->assertSame(0.95, $result[0]->getScore());
        $this->assertSame(['type' => 'first'], $result[0]->getMetadata()->getArrayCopy());
        $this->assertInstanceOf(VectorDocument::class, $result[1]);
        $this->assertSame($uuid2, $result[1]->getId());
        $this->assertSame([0.3, 0.4], $result[1]->getVector()->getData());
        $this->assertSame(0.85, $result[1]->getScore());
        $this->assertSame(['type' => 'second'], $result[1]->getMetadata()->getArrayCopy());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testQueryParsesComplexMetadata()
    {
        $uuid = Uuid::v4()->toString();
        $expectedResponse = [
            [
                'id' => $uuid,
                'embedding' => '[0.1, 0.2, 0.3, 0.4]',
                'metadata' => '{"title": "Test Document", "tags": ["ai", "test"], "score": 0.92}',
                'score' => 0.92,
            ],
        ];
        $httpClient = new MockHttpClient(new JsonMockResponse($expectedResponse));
        $store = $this->createStore($httpClient, 3);

        $result = iterator_to_array($store->query(new VectorQuery(new Vector([1.0, 2.0, 3.0]))));

        $document = $result[0];
        $metadata = $document->getMetadata()->getArrayCopy();
        $this->assertCount(1, $result);
        $this->assertInstanceOf(VectorDocument::class, $document);
        $this->assertSame($uuid, $document->getId());
        $this->assertSame([0.1, 0.2, 0.3, 0.4], $document->getVector()->getData());
        $this->assertSame(0.92, $document->getScore());
        $this->assertSame('Test Document', $metadata['title']);
        $this->assertSame(['ai', 'test'], $metadata['tags']);
        $this->assertSame(0.92, $metadata['score']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = $this->createStore(new MockHttpClient());
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = $this->createStore(new MockHttpClient());
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = $this->createStore(new MockHttpClient());
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    private function createStore(MockHttpClient $httpClient, ?int $vectorDimension = 2): SupabaseStore
    {
        return new SupabaseStore(
            $httpClient,
            'https://test.supabase.co',
            'test-api-key',
            'documents',
            'embedding',
            $vectorDimension,
            'match_documents'
        );
    }
}
