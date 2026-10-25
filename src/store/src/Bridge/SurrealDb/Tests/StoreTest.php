<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\SurrealDb\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\SurrealDb\Store;
use Symfony\AI\Store\Bridge\SurrealDb\StoreFactory;
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
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/signin".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCannotSetupOnValidAuthenticationResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/sql".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCanSetupOnValidAuthenticationAndIndexResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors HNSW DIMENSION 1275 DIST cosine TYPE F32',
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test');

        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreSetupDefinesAnHnswIndex()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => null, 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies', dimensions: 3);

        $store->setup();

        $this->assertCount(2, $requests);
        $this->assertSame('DEFINE INDEX movies_vectors ON movies FIELDS _vectors HNSW DIMENSION 3 DIST cosine TYPE F32', $requests[1]);
    }

    public function testStoreSetupCreatesNamespaceAndDatabaseWhenForced()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => null, 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'my_namespace', 'my_database', 'movies', dimensions: 3);

        $store->setup(['forceDatabaseCreation' => true]);

        $this->assertCount(3, $requests);
        $this->assertSame('DEFINE NAMESPACE IF NOT EXISTS `my_namespace`; DEFINE DATABASE IF NOT EXISTS `my_database`;', $requests[1]);
        $this->assertSame('DEFINE INDEX movies_vectors ON movies FIELDS _vectors HNSW DIMENSION 3 DIST cosine TYPE F32', $requests[2]);
    }

    public function testStoreNormalizesTrailingSlashOnEndpoint()
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrls): JsonMockResponse {
            $requestedUrls[] = $url;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => 'OK', 'status' => 'OK']], ['http_code' => 200]);
        });

        $store = StoreFactory::create('test', 'test', 'test', 'test', 'http://127.0.0.1:8000/', $httpClient);

        $store->setup();

        $this->assertSame([
            'http://127.0.0.1:8000/signin',
            'http://127.0.0.1:8000/sql',
        ], $requestedUrls);
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/sql".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse([
                    'code' => 200,
                    'details' => 'Authentication succeeded.',
                    'token' => 'bar',
                ], [
                    'http_code' => 200,
                ]);
            }

            return new JsonMockResponse([
                [
                    'result' => null,
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test');

        $store->setup();
        $store->drop();

        $this->assertSame(3, $httpClient->getRequestsCount());
        $this->assertSame('POST', $requests[2]['method']);
        $this->assertSame('http://127.0.0.1:8000/sql', $requests[2]['url']);
        $this->assertSame('REMOVE TABLE IF EXISTS vectors;', $requests[2]['body']);
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors HNSW DIMENSION 1275 DIST cosine TYPE F32',
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'test');
        $store->setup();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/key/test".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCannotAddOnInvalidAddResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors HNSW DIMENSION 1275 DIST cosine TYPE F32',
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'test');
        $store->setup();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/key/test".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1)))]);
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors HNSW DIMENSION 1275 DIST cosine TYPE F32',
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                        ],
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                        ],
                    ],
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'test');
        $store->setup();

        $store->add([new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1)))]);

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors HNSW DIMENSION 1275 DIST cosine TYPE F32',
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                        ],
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                        ],
                    ],
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'test');
        $store->setup();

        $store->add([new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1)))]);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/sql".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new VectorQuery(new Vector(array_fill(0, 1275, 0.1)))));
    }

    public function testStoreCanQueryOnValidEmbeddings()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                            'distance' => 0.1,
                        ],
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                            'distance' => 0.1,
                        ],
                    ],
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                            'distance' => 0.1,
                        ],
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            '_vectors' => [0.1, 0.1, 0.1],
                            '_metadata' => [
                                '_id' => Uuid::v4()->toRfc4122(),
                            ],
                            'distance' => 0.1,
                        ],
                    ],
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'test');

        $store->add([new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1)))]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector(array_fill(0, 1275, 0.1)))));

        $this->assertCount(2, $results);
    }

    public function testStoreQueriesWithTheDistanceOfTheIndex()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => [], 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies', metric: 'euclidean');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertSame('SELECT id, _vectors, _metadata, vector::distance::knn() AS distance FROM movies WHERE _vectors <|5,40|> [0.1,0.2,0.3] ORDER BY distance;', $body);
    }

    public function testStoreQueriesWithTheGivenLimit()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => [], 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 10]));

        $this->assertSame('SELECT id, _vectors, _metadata, vector::distance::knn() AS distance FROM movies WHERE _vectors <|10,40|> [0.1,0.2,0.3] ORDER BY distance;', $body);
    }

    public function testStoreQueriesWithAnEfOfAtLeastTheLimit()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => [], 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 50]));

        $this->assertSame('SELECT id, _vectors, _metadata, vector::distance::knn() AS distance FROM movies WHERE _vectors <|50,50|> [0.1,0.2,0.3] ORDER BY distance;', $body);
    }

    public function testStoreQueriesWithAnExplicitEfOfAtLeastTheLimit()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => [], 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 10, 'ef' => 2]));

        $this->assertSame('SELECT id, _vectors, _metadata, vector::distance::knn() AS distance FROM movies WHERE _vectors <|10,10|> [0.1,0.2,0.3] ORDER BY distance;', $body);
    }

    public function testStoreQueriesWithTheGivenEf()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => [], 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 10, 'ef' => 100]));

        $this->assertSame('SELECT id, _vectors, _metadata, vector::distance::knn() AS distance FROM movies WHERE _vectors <|10,100|> [0.1,0.2,0.3] ORDER BY distance;', $body);
    }

    /**
     * @param \Closure(Store): mixed $operation
     */
    #[DataProvider('provideOperationsOnAFreshStore')]
    public function testStoreAuthenticatesBeforeTheFirstRequest(\Closure $operation, string $expectedUrl)
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = ['url' => $url, 'headers' => $options['headers']];

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([['result' => [], 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies', dimensions: 3);

        $operation($store);

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertSame('http://127.0.0.1:8000/signin', $requests[0]['url']);
        $this->assertSame($expectedUrl, $requests[1]['url']);
        $this->assertIsArray($requests[1]['headers']);
        $this->assertContains('Authorization: Bearer bar', $requests[1]['headers']);
    }

    /**
     * @return iterable<string, array{\Closure(Store): mixed, string}>
     */
    public static function provideOperationsOnAFreshStore(): iterable
    {
        yield 'add' => [static fn (Store $store) => $store->add(new VectorDocument('a1', new Vector([0.1, 0.2, 0.3]))), 'http://127.0.0.1:8000/key/movies'];
        yield 'query' => [static fn (Store $store) => iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])))), 'http://127.0.0.1:8000/sql'];
        yield 'remove' => [static fn (Store $store) => $store->remove('a1'), 'http://127.0.0.1:8000/sql'];
    }

    public function testStoreReturnsTheDistanceAsScore()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]),
            new JsonMockResponse([
                [
                    'result' => [
                        [
                            'id' => 'movies:first',
                            '_vectors' => [0.1, 0.2, 0.3],
                            '_metadata' => ['_id' => 'first', 'title' => 'First'],
                            'distance' => 0.12,
                        ],
                        [
                            'id' => 'movies:second',
                            '_vectors' => [0.3, 0.2, 0.1],
                            '_metadata' => ['_id' => 'second', 'title' => 'Second'],
                            'distance' => 0.34,
                        ],
                    ],
                    'status' => 'OK',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test', 'movies');

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame('first', $results[0]->getId());
        $this->assertSame(0.12, $results[0]->getScore());
        $this->assertSame(['title' => 'First'], $results[0]->getMetadata()->getArrayCopy());
        $this->assertSame('second', $results[1]->getId());
        $this->assertSame(0.34, $results[1]->getScore());
    }

    public function testStoreCanRemove()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test');
        $store->remove('123e4567-e89b-12d3-a456-426614174000');

        $this->assertSame('DELETE vectors:`123e4567-e89b-12d3-a456-426614174000`;', $body);
    }

    public function testStoreCanRemoveMultipleIds()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test');
        $store->remove(['first', 'second']);

        $this->assertSame('DELETE vectors:`first`, vectors:`second`;', $body);
    }

    public function testStoreRemoveNeutralizesInjectedId()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = $options['body'] ?? null;

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['token' => 'bar'], ['http_code' => 200]);
            }

            return new JsonMockResponse([], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test');
        $store->remove('a\\`; DELETE vectors; --');

        // backslash and backtick are escaped, so the id cannot close the record key
        $this->assertSame('DELETE vectors:`a\\\\\\`; DELETE vectors; --`;', $body);
    }

    public function testStoreCanClear()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = ['url' => $url, 'body' => $options['body'] ?? null];

            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse([
                    'code' => 200,
                    'details' => 'Authentication succeeded.',
                    'token' => 'bar',
                ], [
                    'http_code' => 200,
                ]);
            }

            return new JsonMockResponse([], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test');

        $store->clear();

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertSame('http://127.0.0.1:8000/sql', $requests[1]['url']);
        $this->assertSame('DELETE vectors;', $requests[1]['body']);
    }

    public function testStorePassesHttpOptionsToAllRequests()
    {
        $timeouts = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$timeouts): JsonMockResponse {
            if (str_ends_with($url, '/signin')) {
                return new JsonMockResponse(['code' => 200, 'details' => 'Authentication succeeded.', 'token' => 'bar'], ['http_code' => 200]);
            }

            $timeouts[] = $options['timeout'] ?? null;

            return new JsonMockResponse([['result' => [], 'status' => 'OK']], ['http_code' => 200]);
        }, 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'test', 'test', 'test', 'test');
        $httpOptions = ['http_options' => ['timeout' => 7.0]];

        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))], $httpOptions);
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), $httpOptions));
        $store->remove('123e4567-e89b-12d3-a456-426614174000', $httpOptions);
        $store->clear($httpOptions);

        $this->assertSame([7.0, 7.0, 7.0, 7.0], $timeouts);
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'test', 'test', 'test_namespace', 'test_database', 'test_table');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'test', 'test', 'test_namespace', 'test_database', 'test_table');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'test', 'test', 'test_namespace', 'test_database', 'test_table');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'token' => 'test-token',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [
                        ['count' => 42],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8000',
            'root',
            'root',
            'test',
            'test',
        );

        $this->assertSame(42, $store->count());
        $this->assertSame(2, $httpClient->getRequestsCount());
    }
}
