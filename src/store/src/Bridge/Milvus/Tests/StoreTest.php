<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Milvus\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Milvus\Store;
use Symfony\AI\Store\Document\VectorDocument;
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
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:19530/v2/vectordb/databases/create".');
        $this->expectExceptionCode(400);
        $store->setup([
            'forceDatabaseCreation' => true,
        ]);
    }

    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'data' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'code' => 0,
                'data' => [],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $store->setup([
            'forceDatabaseCreation' => true,
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:19530/v2/vectordb/databases/drop".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'data' => [],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:19530/v2/vectordb/entities/insert".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'cost' => 0,
                'data' => [
                    'insertCount' => 1,
                    'insertIds' => [
                        Uuid::v4()->toRfc4122(),
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:19530/v2/vectordb/entities/search".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'cost' => 0,
                'data' => [
                    [
                        'id' => Uuid::v4()->toRfc4122(),
                        '_vectors' => [0.1, 0.2, 0.3],
                        '_metadata' => '{"foo":"bar"}',
                        'distance' => 1.0,
                    ],
                    [
                        'id' => Uuid::v4()->toRfc4122(),
                        '_vectors' => [0.11, 0.22, 0.33],
                        '_metadata' => '{"foo":"bar"}',
                        'distance' => 0.8,
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $results = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(2, $results);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotRemoveOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:19530/v2/vectordb/entities/delete".');
        $this->expectExceptionCode(400);
        $store->remove('test-id');
    }

    public function testStoreCanRemoveSingleId()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'data' => [],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $store->remove('test-id');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveMultipleIds()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'data' => [],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $store->remove(['test-id-1', 'test-id-2', 'test-id-3']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveWithEmptyIds()
    {
        $httpClient = new MockHttpClient([], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $store->remove([]);

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveIdWithSpecialCharacters()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'data' => [],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:19530',
            'test',
            'test',
            'test',
        );

        $store->remove('test-id-with-"quotes"');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
