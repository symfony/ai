<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Elasticsearch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Elasticsearch\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupOnExistingIndex()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9200', 'foo');
        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 404,
            ]),
            new JsonMockResponse([
                'acknowledged' => true,
                'shards_acknowledged' => true,
                'index' => 'foo',
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9200', 'foo');
        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCanSetupWithExtraOptions()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 400,
            ]),
            new JsonMockResponse([
                'acknowledged' => true,
                'shards_acknowledged' => true,
                'index' => 'foo',
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9200', 'foo');

        $store->setup([
            'dimensions' => 768,
            'similarity' => 'dot_product',
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnUndefinedIndex()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 404,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9200', 'foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The index "foo" does not exist.');
        $this->expectExceptionCode(0);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'acknowledged' => true,
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9200', 'foo');
        $store->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCanSave()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'took' => 100,
                'errors' => false,
                'items' => [
                    [
                        'index' => [
                            '_index' => 'foo',
                            '_id' => Uuid::v7()->toRfc4122(),
                            '_version' => 1,
                            'result' => 'created',
                            '_shards' => [],
                            'status' => 201,
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9200', 'foo');
        $store->add([new VectorDocument(Uuid::v7(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'took' => 100,
                'errors' => false,
                'hits' => [
                    'total' => [
                        'value' => 1,
                        'relation' => 'eq',
                    ],
                    'hits' => [
                        [
                            '_index' => 'foo',
                            '_id' => Uuid::v7()->toRfc4122(),
                            '_score' => 1.4363918,
                            '_source' => [
                                '_vectors' => [0.1, 0.2, 0.3],
                                'metadata' => json_encode([
                                    'foo' => 'bar',
                                ]),
                            ],
                        ],
                        [
                            '_index' => 'foo',
                            '_id' => Uuid::v7()->toRfc4122(),
                            '_score' => 1.3363918,
                            '_source' => [
                                '_vectors' => [0.1, 0.4, 0.3],
                                'metadata' => json_encode([
                                    'foo' => 'bar',
                                ]),
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9200', 'foo');
        $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(2, iterator_to_array($results));
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:9200', 'test-index');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:9200', 'test-index');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:9200', 'test-index');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
