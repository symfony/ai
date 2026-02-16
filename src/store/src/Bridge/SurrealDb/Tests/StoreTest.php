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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\SurrealDb\Store;
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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test');

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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test');

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
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors MTREE DIMENSION 1275 DIST cosine TYPE F32',
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test');

        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/key/test".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
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
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors MTREE DIMENSION 1275 DIST cosine TYPE F32',
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test');

        $store->setup();
        $store->drop();

        $this->assertSame(3, $httpClient->getRequestsCount());
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
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors MTREE DIMENSION 1275 DIST cosine TYPE F32',
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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', 'test');
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
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors MTREE DIMENSION 1275 DIST cosine TYPE F32',
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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', 'test');
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
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors MTREE DIMENSION 1275 DIST cosine TYPE F32',
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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', 'test');
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
                    'result' => 'DEFINE INDEX test_vectors ON movies FIELDS _vectors MTREE DIMENSION 1275 DIST cosine TYPE F32',
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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', 'test');
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

        $store = new Store($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', 'test');

        $store->add([new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1)))]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector(array_fill(0, 1275, 0.1)))));

        $this->assertCount(2, $results);
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8000', 'test', 'test', 'test_namespace', 'test_database', 'test_table');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8000', 'test', 'test', 'test_namespace', 'test_database', 'test_table');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8000', 'test', 'test', 'test_namespace', 'test_database', 'test_table');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
