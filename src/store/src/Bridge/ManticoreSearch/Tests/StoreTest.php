<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ManticoreSearch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\ManticoreSearch\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupWithExtraOptions()
    {
        $store = new Store(new MockHttpClient(), 'bar', 'random');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $store->setup([
            'foo' => 'bar',
        ]);
    }

    public function testStoreCannotSetupOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new MockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9308/cli".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Query OK, 0 rows affected (0.006 sec)'.\PHP_EOL, [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new MockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($mockHttpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9308/cli".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Query OK, 1 rows affected (0.006 sec)'.\PHP_EOL, [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($mockHttpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9308/bulk".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'items' => [
                    [
                        'bulk' => [
                            'table' => 'bar',
                            '_id' => 1,
                            'created' => 1,
                            'deleted' => 0,
                            'updated' => 0,
                            'result' => 'created',
                            'status' => 201,
                        ],
                    ],
                ],
                'current_line' => 4,
                'skipped_lines' => 0,
                'errors' => false,
                'error' => '',
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($mockHttpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9308/search".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'took' => 0,
                'timed_out' => false,
                'hits' => [
                    'total' => 1,
                    'total_relation' => 'eq',
                    'hits' => [
                        [
                            '_id' => 1,
                            '_score' => 1,
                            '_knn_dist' => 0.12345678,
                            '_source' => [
                                'uuid' => Uuid::v7()->toRfc4122(),
                                'random' => [0.1, 0.2, 0.3],
                                'metadata' => [
                                    'foo' => 'bar',
                                ],
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');
        $documents = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(1, $documents);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanClear()
    {
        $requestedMethod = null;
        $requestedUrl = null;
        $requestedBody = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedMethod, &$requestedUrl, &$requestedBody): MockResponse {
            $requestedMethod = $method;
            $requestedUrl = $url;
            $requestedBody = $options['body'];

            return new MockResponse('Query OK, 0 rows affected (0.006 sec)'.\PHP_EOL, [
                'http_code' => 200,
            ]);
        });

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');
        $store->clear();

        $this->assertSame('POST', $requestedMethod);
        $this->assertSame('http://127.0.0.1:9308/cli', $requestedUrl);
        $this->assertSame('TRUNCATE TABLE bar', $requestedBody);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testFindSearchesTheDocumentByItsUuid()
    {
        $response = new JsonMockResponse([
            'took' => 0,
            'timed_out' => false,
            'hits' => [
                'total' => 1,
                'total_relation' => 'eq',
                'hits' => [
                    [
                        '_id' => 1,
                        '_score' => 1,
                        '_source' => [
                            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                            'random' => [0.1, 0.2, 0.3],
                            'metadata' => ['foo' => 'bar'],
                        ],
                    ],
                ],
            ],
        ]);
        $httpClient = new MockHttpClient($response);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');
        $document = $store->find('123e4567-e89b-12d3-a456-426614174000');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('http://127.0.0.1:9308/search', $response->getRequestUrl());
        $this->assertSame([
            'table' => 'bar',
            'query' => ['equals' => ['uuid' => '123e4567-e89b-12d3-a456-426614174000']],
            'limit' => 1,
        ], json_decode($response->getRequestOptions()['body'], true));

        $this->assertNotNull($document);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $document->getId());
        $this->assertSame([0.1, 0.2, 0.3], $document->getVector()->getData());
        $this->assertSame(['foo' => 'bar'], $document->getMetadata()->getArrayCopy());
        $this->assertNull($document->getScore());
    }

    public function testFindReturnsNullForAnUnknownUuid()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['took' => 0, 'timed_out' => false, 'hits' => ['total' => 0, 'total_relation' => 'eq', 'hits' => []]]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $this->assertNull($store->find('123e4567-e89b-12d3-a456-426614174000'));
    }

    public function testFindSimilarLeavesTheDocumentOut()
    {
        $searchResponse = new JsonMockResponse(['hits' => ['hits' => [
            ['_id' => 1, '_score' => 1, '_knn_dist' => 0.0, '_source' => ['uuid' => 'itself', 'random' => [0.1, 0.2, 0.3], 'metadata' => []]],
            ['_id' => 2, '_score' => 1, '_knn_dist' => 0.1, '_source' => ['uuid' => 'other', 'random' => [0.1, 0.2, 0.4], 'metadata' => []]],
        ]]]);
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['hits' => ['hits' => [
                ['_id' => 1, '_score' => 1, '_source' => ['uuid' => 'itself', 'random' => [0.1, 0.2, 0.3], 'metadata' => []]],
            ]]]),
            $searchResponse,
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');
        $documents = iterator_to_array($store->findSimilar('itself', ['limit' => 1]), false);

        $body = json_decode($searchResponse->getRequestOptions()['body'], true);
        $this->assertSame([0.1, 0.2, 0.3], $body['knn']['query']);
        $this->assertSame(2, $body['limit']);
        $this->assertCount(1, $documents);
        $this->assertSame('other', $documents[0]->getId());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_index');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_index');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_index');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'total' => 0,
                    'error' => '',
                    'warning' => '',
                    'columns' => [['cnt' => ['type' => 'bigint']]],
                    'data' => [['cnt' => 42]],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store(ScopingHttpClient::forBaseUri($httpClient, 'http://127.0.0.1:9308/'), 'bar', 'random');

        $this->assertSame(42, $store->count());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testQueryReturnsAsManyHitsAsTheLimit()
    {
        $response = new JsonMockResponse(['hits' => ['hits' => []]]);
        $store = new Store(ScopingHttpClient::forBaseUri(new MockHttpClient($response), 'http://127.0.0.1:9308/'), 'bar', 'random');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 30]));

        $body = json_decode($response->getRequestOptions()['body'], true);
        $this->assertSame(30, $body['knn']['k']);
        $this->assertSame(30, $body['limit']);
    }
}
