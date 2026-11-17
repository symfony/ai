<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Typesense\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Typesense\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupOnExistingCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'A collection with name "test" already exists.',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8108/collections".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCannotSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'name' => 'test',
                'num_documents' => 0,
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnUndefinedCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 404,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "http://127.0.0.1:8108/collections/test".');
        $this->expectExceptionCode(404);
        $store->drop();
    }

    public function testStoreCanDropOnExistingCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8108/collections/test/documents".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8108/multi_search".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    [
                        'hits' => [
                            [
                                'document' => [
                                    'id' => Uuid::v4()->toRfc4122(),
                                    'vector' => [0.1, 0.2, 0.3],
                                    'metadata' => '{"foo":"bar"}',
                                ],
                                'vector_distance' => 1.0,
                            ],
                            [
                                'document' => [
                                    'id' => Uuid::v4()->toRfc4122(),
                                    'vector' => [0.1, 0.2, 0.3],
                                    'metadata' => '{"foo":"bar"}',
                                ],
                                'vector_distance' => 1.0,
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemove()
    {
        $url = null;
        $httpClient = new MockHttpClient(static function (string $method, string $u, array $options) use (&$url): JsonMockResponse {
            $url = $u;

            return new JsonMockResponse([], ['http_code' => 200]);
        }, 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');
        $id = Uuid::v4()->toRfc4122();
        $store->remove($id);

        $this->assertStringContainsString(\sprintf('filter_by=id:[%s]', $id), urldecode((string) $url));
    }

    public function testStoreRemoveRejectsInjectedId()
    {
        $store = new Store(new MockHttpClient(), 'test');

        $this->expectException(InvalidArgumentException::class);

        $store->remove('1] || num_docs:>=0 || id:[2');
    }

    public function testStoreCanClear()
    {
        $requestedMethod = null;
        $url = null;
        $httpClient = new MockHttpClient(static function (string $method, string $u, array $options) use (&$requestedMethod, &$url): JsonMockResponse {
            $requestedMethod = $method;
            $url = $u;

            return new JsonMockResponse(['num_deleted' => 2], ['http_code' => 200]);
        }, 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');
        $store->clear();

        $this->assertSame('DELETE', $requestedMethod);
        $this->assertSame('http://127.0.0.1:8108/collections/test/documents?truncate=true', urldecode((string) $url));
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testFindRetrievesTheDocumentById()
    {
        $response = new JsonMockResponse([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            '_vectors' => [0.1, 0.2, 0.3],
            'metadata' => '{"foo":"bar"}',
        ]);
        $httpClient = new MockHttpClient($response, 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');
        $document = $store->find('123e4567-e89b-12d3-a456-426614174000');

        $this->assertSame('GET', $response->getRequestMethod());
        $this->assertSame('http://127.0.0.1:8108/collections/test/documents/123e4567-e89b-12d3-a456-426614174000', $response->getRequestUrl());

        $this->assertNotNull($document);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $document->getId());
        $this->assertSame([0.1, 0.2, 0.3], $document->getVector()->getData());
        $this->assertSame(['foo' => 'bar'], $document->getMetadata()->getArrayCopy());
        $this->assertNull($document->getScore());
    }

    public function testFindEncodesTheIdInThePath()
    {
        $response = new JsonMockResponse(['id' => 'a/b', '_vectors' => [0.1, 0.2, 0.3], 'metadata' => '{}']);
        $store = new Store(new MockHttpClient($response, 'http://127.0.0.1:8108'), 'test');

        $store->find('a/b');

        $this->assertSame('http://127.0.0.1:8108/collections/test/documents/a%2Fb', $response->getRequestUrl());
    }

    public function testFindReturnsNullForAnUnknownId()
    {
        $collectionResponse = new JsonMockResponse(['name' => 'test', 'num_documents' => 3]);
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['message' => 'Could not find a document with id: unknown'], ['http_code' => 404]),
            $collectionResponse,
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->assertNull($store->find('unknown'));
        $this->assertSame('GET', $collectionResponse->getRequestMethod());
        $this->assertSame('http://127.0.0.1:8108/collections/test', $collectionResponse->getRequestUrl());
    }

    public function testFindThrowsWhenTheCollectionDoesNotExist()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['message' => 'Collection not found'], ['http_code' => 404]),
            new JsonMockResponse(['message' => 'Not Found'], ['http_code' => 404]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "http://127.0.0.1:8108/collections/test".');

        $store->find('unknown');
    }

    public function testFindThrowsOnServerError()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['message' => 'Forbidden - a valid `x-typesense-api-key` header must be sent.'], ['http_code' => 401]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 401 returned for "http://127.0.0.1:8108/collections/test/documents/foo".');

        $store->find('foo');
    }

    public function testFindSimilarLeavesTheDocumentOut()
    {
        $searchResponse = new JsonMockResponse(['results' => [['hits' => [
            ['document' => ['id' => 'itself', '_vectors' => [0.1, 0.2, 0.3], 'metadata' => '{}'], 'vector_distance' => 0.0],
            ['document' => ['id' => 'other', '_vectors' => [0.1, 0.2, 0.4], 'metadata' => '{}'], 'vector_distance' => 0.1],
        ]]]]);
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => 'itself', '_vectors' => [0.1, 0.2, 0.3], 'metadata' => '{}']),
            $searchResponse,
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');
        $documents = iterator_to_array($store->findSimilar('itself', ['limit' => 1]), false);

        $body = $searchResponse->getRequestOptions()['body'];
        $this->assertIsString($body);
        $this->assertSame(['searches' => [[
            'collection' => 'test',
            'q' => '*',
            'vector_query' => '_vectors:([0.1, 0.2, 0.3], k:2)',
            'per_page' => 2,
        ]]], json_decode($body, true));
        $this->assertCount(1, $documents);
        $this->assertSame('other', $documents[0]->getId());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_collection');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_collection');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_collection');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'name' => 'test',
                'num_documents' => 42,
                'fields' => [],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store($httpClient, 'test');

        $this->assertSame(42, $store->count());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testQueryReturnsAsManyHitsAsTheLimit()
    {
        $response = new JsonMockResponse(['results' => [['hits' => []]]]);
        $store = new Store(new MockHttpClient($response), 'test');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 20]));

        $body = $response->getRequestOptions()['body'];
        $this->assertIsString($body);
        $this->assertSame(['searches' => [[
            'collection' => 'test',
            'q' => '*',
            'vector_query' => '_vectors:([0.1, 0.2, 0.3], k:20)',
            'per_page' => 20,
        ]]], json_decode($body, true));
    }

    public function testQueryThrowsWhenTheLimitExceedsThePageSizeCap()
    {
        $store = new Store(new MockHttpClient(), 'test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "limit" option must not exceed 250, 251 given.');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 251]));
    }
}
