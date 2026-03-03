<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Neo4j\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Neo4j\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7474');

        $store = new Store($httpClient, 'http://127.0.0.1:7474', 'symfony', 'symfony', 'symfony', 'symfony', 'symfony');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7474/db/symfony/query/v2".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCannotSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    'fields' => [],
                    'values' => [],
                ],
                'bookmarks' => [
                    'FB:kcwQ5zbxUD1ESXmS6UjG2xKCZMkAoJB=',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'data' => [
                    'fields' => [],
                    'values' => [],
                ],
                'notifications' => [
                    [
                        'code' => 'Neo.ClientNotification.Schema.IndexOrConstraintAlreadyExists',
                        'description' => '`VECTOR INDEX movies FOR (e:symfony) ON (e.symfony)` already exists.',
                        'severity' => 'INFORMATION',
                        'title' => '"`CREATE VECTOR INDEX movies IF NOT EXISTS FOR (symfony:symfony) ON (symfony.symfony) OPTIONS {indexConfig: {`vector.dimensions`: 1536, `vector.similarity_function`: "cosine", `vector.quantization.enabled`: false}}` has no effect.',
                        'position' => null,
                        'category' => 'SCHEMA',
                    ],
                ],
                'bookmarks' => [
                    'FB:kcwQ5zbxUD1ESXmS6UjG2xKCZMkAoJA=',
                ],
            ], [
                'http_code' => 202,
            ]),
        ], 'http://127.0.0.1:7474');

        $store = new Store($httpClient, 'http://127.0.0.1:7474', 'symfony', 'symfony', 'symfony', 'symfony', 'symfony');

        $store->setup();
        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7474');

        $store = new Store($httpClient, 'http://127.0.0.1:7474', 'symfony', 'symfony', 'symfony', 'symfony', 'symfony');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7474/db/symfony/query/v2".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:7474');

        $store = new Store($httpClient, 'http://127.0.0.1:7474', 'symfony', 'symfony', 'symfony', 'symfony', 'symfony');

        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    'fields' => [],
                    'values' => [],
                ],
                'bookmarks' => [
                    'FB:kcwQ5zbxUD1ESXmS6UjG2xKCZMkAoJB=',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'data' => [
                    'fields' => [
                        'n',
                    ],
                    'values' => [
                        [
                            [
                                'elementId' => '4:'.Uuid::v4()->toRfc4122(),
                                'labels' => [
                                    'symfony',
                                ],
                                'properties' => [
                                    'embeddings' => [0.1, 0.2, 0.3],
                                    'metadata' => [],
                                    'id' => Uuid::v4()->toRfc4122(),
                                ],
                            ],
                        ],
                    ],
                ],
                'bookmarks' => [
                    'FB:kcwQdJAosIhGT0yRm+Na1gMjaQqQ',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'data' => [
                    'fields' => [
                        'n',
                    ],
                    'values' => [
                        [
                            [
                                'elementId' => '4:'.Uuid::v4()->toRfc4122(),
                                'labels' => [
                                    'symfony',
                                ],
                                'properties' => [
                                    'embeddings' => [0.1, 0.2, 0.3],
                                    'metadata' => [],
                                    'id' => Uuid::v4()->toRfc4122(),
                                ],
                            ],
                        ],
                    ],
                ],
                'bookmarks' => [
                    'FB:kcwQdJAosIhGT0yRm+Na1gMjaQqS',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:7474');

        $store = new Store($httpClient, 'http://127.0.0.1:7474', 'symfony', 'symfony', 'symfony', 'symfony', 'symfony');

        $store->setup();
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    'fields' => [],
                    'values' => [],
                ],
                'bookmarks' => [
                    'FB:kcwQ5zbxUD1ESXmS6UjG2xKCZMkAoJB=',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'data' => [
                    'fields' => [
                        'n',
                    ],
                    'values' => [
                        [
                            [
                                'elementId' => '4:'.Uuid::v4()->toRfc4122(),
                                'labels' => [
                                    'symfony',
                                ],
                                'properties' => [
                                    'embeddings' => [0.1, 0.2, 0.3],
                                    'metadata' => [],
                                    'id' => Uuid::v4()->toRfc4122(),
                                ],
                            ],
                        ],
                    ],
                ],
                'bookmarks' => [
                    'FB:kcwQdJAosIhGT0yRm+Na1gMjaQqR',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'data' => [
                    'fields' => [
                        'node',
                        'score',
                    ],
                    'values' => [
                        [
                            [
                                'elementId' => '4:'.Uuid::v4()->toRfc4122(),
                                'labels' => [
                                    'symfony',
                                ],
                                'properties' => [
                                    'embeddings' => [0.1, 0.2, 0.3],
                                    'metadata' => json_encode([
                                        'foo' => 'bar',
                                    ]),
                                    'id' => Uuid::v4()->toRfc4122(),
                                ],
                            ],
                            0.1,
                        ],
                        [
                            [
                                'elementId' => '4:'.Uuid::v4()->toRfc4122(),
                                'labels' => [
                                    'symfony',
                                ],
                                'properties' => [
                                    'embeddings' => [0.1, 0.2, 0.3],
                                    'metadata' => json_encode([
                                        'foo' => 'bar',
                                    ]),
                                    'id' => Uuid::v4()->toRfc4122(),
                                ],
                            ],
                            0.1,
                        ],
                    ],
                ],
                'bookmarks' => [
                    'FB:kcwQdJAosIhGT0yRm+Na1gMjaQqT',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:7474');

        $store = new Store($httpClient, 'http://127.0.0.1:7474', 'symfony', 'symfony', 'symfony', 'symfony', 'symfony');

        $store->setup();
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'bolt://localhost:7687', 'neo4j', 'password', 'neo4j', 'vector_index', 'Document');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'bolt://localhost:7687', 'neo4j', 'password', 'neo4j', 'vector_index', 'Document');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'bolt://localhost:7687', 'neo4j', 'password', 'neo4j', 'vector_index', 'Document');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
