<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Typesense\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Typesense\Store;
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
    public function testStoreCannotSetupOnExistingCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'A collection with name "test" already exists.',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8108/collections".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCannotSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'name' => 'test',
                'num_documents' => 0,
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
            'test',
            'test',
        );

        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnUndefinedCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 404,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "http://127.0.0.1:8108/collections/test".');
        $this->expectExceptionCode(404);
        $store->drop();
    }

    public function testStoreCanDropOnExistingCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
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
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8108/collections/test/documents".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
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
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8108/multi_search".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    [
                        'hits' => [
                            [
                                'document' => [
                                    'id' => Uuid::v4()->toRfc4122(),
                                    'vector' => [0.1, 0.2, 0.3],
                                    'metadata' => '{"foo":"bar"}',
                                ],
                                'vector_distance' => 1.0,
                            ],
                            [
                                'document' => [
                                    'id' => Uuid::v4()->toRfc4122(),
                                    'vector' => [0.1, 0.2, 0.3],
                                    'metadata' => '{"foo":"bar"}',
                                ],
                                'vector_distance' => 1.0,
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8108');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:8108',
            'test',
            'test',
        );

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8108', 'test-key', 'test_collection');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8108', 'test-key', 'test_collection');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8108', 'test-key', 'test_collection');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
