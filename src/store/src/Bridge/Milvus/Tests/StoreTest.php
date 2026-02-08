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
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
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

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:19530', 'test-api-key', 'default', 'test_collection');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:19530', 'test-api-key', 'default', 'test_collection');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:19530', 'test-api-key', 'default', 'test_collection');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
