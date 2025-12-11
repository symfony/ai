<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Meilisearch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Meilisearch\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'index_creation_failed',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#index_creation_failed',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCannotSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'taskUid' => 1,
                'indexUid' => 'test',
                'status' => 'enqueued',
                'type' => 'indexCreation',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
            new JsonMockResponse([
                'taskUid' => 2,
                'indexUid' => 'test',
                'status' => 'enqueued',
                'type' => 'indexUpdate',
                'enqueuedAt' => '2025-01-01T01:00:00Z',
            ], [
                'http_code' => 202,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
        );

        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'index_not_found',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#index_not_found',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes/test".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'invalid_document_fields',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#invalid_document_fields',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes/test/documents".');
        $this->expectExceptionCode(400);
        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])));
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'taskUid' => 1,
                'indexUid' => 'test',
                'status' => 'enqueued',
                'type' => 'documentAdditionOrUpdate',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
        );

        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'invalid_search_hybrid_query',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#invalid_search_hybrid_query',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes/test/search".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'hits' => [
                    [
                        'id' => Uuid::v4()->toRfc4122(),
                        '_vectors' => [
                            'default' => [
                                'embeddings' => [0.1, 0.2, 0.3],
                                'regenerate' => false,
                            ],
                        ],
                        '_rankingScore' => 0.95,
                    ],
                    [
                        'id' => Uuid::v4()->toRfc4122(),
                        '_vectors' => [
                            'default' => [
                                'embeddings' => [0.4, 0.5, 0.6],
                                'regenerate' => false,
                            ],
                        ],
                        '_rankingScore' => 0.85,
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
            embeddingsDimension: 3,
        );

        $vectors = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertCount(2, $vectors);
        $this->assertInstanceOf(VectorDocument::class, $vectors[0]);
        $this->assertInstanceOf(VectorDocument::class, $vectors[1]);
        $this->assertSame(0.95, $vectors[0]->score);
        $this->assertSame(0.85, $vectors[1]->score);
    }

    public function testMetadataWithoutIDRankingandVector()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'hits' => [
                    [
                        'id' => Uuid::v4()->toRfc4122(),
                        'title' => 'The Matrix',
                        'description' => 'A science fiction action film.',
                        '_vectors' => [
                            'default' => [
                                'embeddings' => [0.1, 0.2, 0.3],
                                'regenerate' => false,
                            ],
                        ],
                        '_rankingScore' => 0.95,
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:7700',
            'test',
            'test',
            embeddingsDimension: 3,
        );

        $vectors = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));
        $expected = [
            'title' => 'The Matrix',
            'description' => 'A science fiction action film.',
        ];

        $this->assertSame($expected, $vectors[0]->metadata->getArrayCopy());
    }

    public function testConstructorWithValidSemanticRatio()
    {
        $httpClient = new MockHttpClient();

        $store = new Store($httpClient, 'http://localhost:7700', 'key', 'index', semanticRatio: 0.5);

        $this->assertInstanceOf(Store::class, $store);
    }

    public function testConstructorThrowsExceptionForInvalidSemanticRatio()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The semantic ratio must be between 0.0 and 1.0');

        $httpClient = new MockHttpClient();
        new Store($httpClient, 'http://localhost:7700', 'key', 'index', semanticRatio: 1.5);
    }

    public function testConstructorThrowsExceptionForNegativeSemanticRatio()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The semantic ratio must be between 0.0 and 1.0');

        $httpClient = new MockHttpClient();
        new Store($httpClient, 'http://localhost:7700', 'key', 'index', semanticRatio: -0.1);
    }

    public function testQueryUsesDefaultSemanticRatio()
    {
        $responses = [
            new MockResponse(json_encode([
                'hits' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        '_vectors' => [
                            'default' => [
                                'embeddings' => [0.1, 0.2, 0.3],
                            ],
                        ],
                        '_rankingScore' => 0.95,
                        'content' => 'Test document',
                    ],
                ],
            ])),
        ];

        $httpClient = new MockHttpClient($responses);
        $store = new Store($httpClient, 'http://localhost:7700', 'key', 'index', semanticRatio: 0.7);

        $vector = new Vector([0.1, 0.2, 0.3]);
        iterator_to_array($store->query($vector));

        $request = $httpClient->getRequestsCount() > 0 ? $responses[0]->getRequestOptions() : null;
        $this->assertNotNull($request);

        $body = json_decode($request['body'], true);
        $this->assertSame(0.7, $body['hybrid']['semanticRatio']);
    }

    public function testQueryCanOverrideSemanticRatio()
    {
        $responses = [
            new MockResponse(json_encode([
                'hits' => [],
            ])),
        ];

        $httpClient = new MockHttpClient($responses);
        $store = new Store($httpClient, 'http://localhost:7700', 'key', 'index', semanticRatio: 0.5);

        iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3]), ['semanticRatio' => 0.2]));

        $request = $responses[0]->getRequestOptions();
        $body = json_decode($request['body'], true);

        $this->assertSame(0.2, $body['hybrid']['semanticRatio']);
    }

    public function testQueryThrowsExceptionForInvalidSemanticRatioOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The semantic ratio must be between 0.0 and 1.0');

        $httpClient = new MockHttpClient();
        $store = new Store($httpClient, 'http://localhost:7700', 'key', 'index');

        $vector = new Vector([0.1, 0.2, 0.3]);
        iterator_to_array($store->query($vector, ['semanticRatio' => 2.0]));
    }

    public function testQueryWithPureKeywordSearch()
    {
        $responses = [
            new MockResponse(json_encode([
                'hits' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        '_vectors' => [
                            'default' => [
                                'embeddings' => [0.1, 0.2, 0.3],
                            ],
                        ],
                        '_rankingScore' => 0.85,
                        'title' => 'Symfony Framework',
                    ],
                ],
            ])),
        ];

        $httpClient = new MockHttpClient($responses);
        $store = new Store($httpClient, 'http://localhost:7700', 'key', 'index');

        $vector = new Vector([0.1, 0.2, 0.3]);
        $results = iterator_to_array($store->query($vector, ['semanticRatio' => 0.0]));

        $this->assertCount(1, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);

        $request = $responses[0]->getRequestOptions();
        $body = json_decode($request['body'], true);
        $this->assertSame(0.0, $body['hybrid']['semanticRatio']);
    }

    public function testQueryWithBalancedHybridSearch()
    {
        $responses = [
            new MockResponse(json_encode([
                'hits' => [],
            ])),
        ];

        $httpClient = new MockHttpClient($responses);
        $store = new Store($httpClient, 'http://localhost:7700', 'key', 'index', semanticRatio: 0.5);

        $vector = new Vector([0.1, 0.2, 0.3]);
        iterator_to_array($store->query($vector));

        $request = $responses[0]->getRequestOptions();
        $body = json_decode($request['body'], true);

        $this->assertSame(0.5, $body['hybrid']['semanticRatio']);
    }
}
