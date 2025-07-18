<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\SurrealDB;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\SurrealDB\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Store::class)]
final class StoreTest extends TestCase
{
    public function testStoreCannotInitializeOnInvalidResponse(): void
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:8000/signin".');
        self::expectExceptionCode(400);
        $store->initialize();
    }

    public function testStoreCannotInitializeOnValidAuthenticationResponse(): void
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
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:8000/sql".');
        self::expectExceptionCode(400);
        $store->initialize();
    }

    public function testStoreCannotInitializeOnValidAuthenticationAndIndexResponse(): void
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
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test');

        $store->initialize();

        self::assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse(): void
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
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test', 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:8000/key/test".');
        self::expectExceptionCode(400);
        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])));
    }

    public function testStoreCannotAddOnInvalidAddResponse(): void
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
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test', 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:8000/key/test".');
        self::expectExceptionCode(400);
        $store->add(new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1))));
    }

    public function testStoreCanAdd(): void
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
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test', 'test');

        $store->add(new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1))));

        self::assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse(): void
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
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test', 'test');

        $store->add(new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1))));

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:8000/sql".');
        self::expectExceptionCode(400);
        $store->query(new Vector(array_fill(0, 1275, 0.1)));
    }

    public function testStoreCanQueryOnValidEmbeddings(): void
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
        ], 'http://localhost:8000');

        $store = new Store($httpClient, 'http://localhost:8000', 'test', 'test', 'test', 'test', 'test');

        $store->add(new VectorDocument(Uuid::v4(), new Vector(array_fill(0, 1275, 0.1))));

        $results = $store->query(new Vector(array_fill(0, 1275, 0.1)));

        self::assertCount(2, $results);
    }
}
