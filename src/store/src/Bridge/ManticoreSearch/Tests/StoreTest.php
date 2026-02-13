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
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupWithExtraOptions()
    {
        $store = new Store(new MockHttpClient(), 'http://127.0.0.1:9308', 'bar', 'random');

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

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');

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

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');

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

        $store = new Store($mockHttpClient, 'http://127.0.0.1:9308', 'bar', 'random');

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

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');

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

        $store = new Store($mockHttpClient, 'http://127.0.0.1:9308', 'bar', 'random');

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

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');
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

        $store = new Store($mockHttpClient, 'http://127.0.0.1:9308', 'bar', 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9308/search".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));
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

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');
        $documents = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(1, $documents);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotRemoveOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store($mockHttpClient, 'http://127.0.0.1:9308', 'bar', 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9308/bulk".');
        $this->expectExceptionCode(400);
        $store->remove('test-id');
    }

    public function testStoreCanRemoveSingleId()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'items' => [
                    [
                        'bulk' => [
                            'table' => 'bar',
                            '_id' => 1,
                            'created' => 0,
                            'deleted' => 1,
                            'updated' => 0,
                            'result' => 'deleted',
                            'status' => 200,
                        ],
                    ],
                ],
                'errors' => false,
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');
        $store->remove('test-id');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveMultipleIds()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'items' => [
                    [
                        'bulk' => [
                            'table' => 'bar',
                            '_id' => 1,
                            'created' => 0,
                            'deleted' => 3,
                            'updated' => 0,
                            'result' => 'deleted',
                            'status' => 200,
                        ],
                    ],
                ],
                'errors' => false,
            ], [
                'http_code' => 200,
            ]),
        ]);

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');
        $store->remove(['test-id-1', 'test-id-2', 'test-id-3']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveWithEmptyArray()
    {
        $httpClient = new MockHttpClient();

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');
        $store->remove([]);

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveWithChunking()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'items' => [],
                'errors' => false,
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'items' => [],
                'errors' => false,
            ], [
                'http_code' => 200,
            ]),
        ]);

        $ids = [];
        for ($i = 0; $i < 1001; ++$i) {
            $ids[] = 'test-id-'.$i;
        }

        $store = new Store($httpClient, 'http://127.0.0.1:9308', 'bar', 'random');
        $store->remove($ids);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }
}
