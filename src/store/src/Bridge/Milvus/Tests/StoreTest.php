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
use Symfony\AI\Store\Exception\RuntimeException;
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
            'test',
            'test',
        );

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemove()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = json_decode($options['body'] ?? '{}', true);

            return new JsonMockResponse(['code' => 0], ['http_code' => 200]);
        }, 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');
        $store->remove('abc');

        $this->assertSame('id in ["abc"]', $body['filter']);
    }

    public function testStoreRemoveNeutralizesInjectedId()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = json_decode($options['body'] ?? '{}', true);

            return new JsonMockResponse(['code' => 0], ['http_code' => 200]);
        }, 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');

        // A trailing backslash must not escape the closing quote of the string literal.
        $store->remove(['a\\', ' or id != "x']);

        $this->assertSame('id in ["a\\\\"," or id != \"x"]', $body['filter']);
    }

    public function testStoreCanClear()
    {
        $requestedUrl = null;
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedUrl, &$body): JsonMockResponse {
            $requestedUrl = $url;
            $body = json_decode($options['body'] ?? '{}', true);

            return new JsonMockResponse(['code' => 0], ['http_code' => 200]);
        }, 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');
        $store->clear();

        $this->assertSame('http://127.0.0.1:19530/v2/vectordb/entities/delete', $requestedUrl);
        $this->assertSame('test_collection', $body['collectionName']);
        $this->assertSame("id != ''", $body['filter']);
    }

    public function testStoreCanFind()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:19530/v2/vectordb/entities/query', $url);
            $this->assertSame([
                'collectionName' => 'test_collection',
                'filter' => 'id in ["123e4567-e89b-12d3-a456-426614174000"]',
                'outputFields' => ['id', '_metadata', '_vectors'],
            ], json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR));

            return new JsonMockResponse([
                'code' => 0,
                'cost' => 0,
                'data' => [
                    [
                        'id' => '123e4567-e89b-12d3-a456-426614174000',
                        '_vectors' => [0.1, 0.2, 0.3],
                        '_metadata' => '{"foo":"bar"}',
                    ],
                ],
            ]);
        }, 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');

        $document = $store->find('123e4567-e89b-12d3-a456-426614174000');

        $this->assertNotNull($document);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $document->getId());
        $this->assertSame([0.1, 0.2, 0.3], $document->getVector()->getData());
        $this->assertSame(['foo' => 'bar'], $document->getMetadata()->getArrayCopy());
        $this->assertNull($document->getScore());
    }

    public function testStoreFindEscapesTheId()
    {
        $body = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): JsonMockResponse {
            $body = json_decode($options['body'], true);

            return new JsonMockResponse(['code' => 0, 'data' => []]);
        }, 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');
        $store->find('a"b\\');

        $this->assertSame('id in ["a\"b\\\\"]', $body['filter']);
    }

    public function testStoreReturnsNullWhenFindingAnUnknownDocument()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'cost' => 0,
                'data' => [],
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');

        $this->assertNull($store->find('123e4567-e89b-12d3-a456-426614174000'));
    }

    public function testStoreCannotFindOnFailedResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 100,
                'message' => "can't find collection[database=default][collection=test_collection]",
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Milvus request failed with code 100: "can\'t find collection[database=default][collection=test_collection]".');
        $store->find('123e4567-e89b-12d3-a456-426614174000');
    }

    public function testStoreCanFindSimilarDocuments()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'data' => [
                    [
                        'id' => '123e4567-e89b-12d3-a456-426614174000',
                        '_vectors' => [0.1, 0.2, 0.3],
                        '_metadata' => '{}',
                    ],
                ],
            ]),
            function (string $method, string $url, array $options): JsonMockResponse {
                $this->assertSame('http://127.0.0.1:19530/v2/vectordb/entities/search', $url);

                $body = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
                $this->assertSame([[0.1, 0.2, 0.3]], $body['data']);
                $this->assertSame(2, $body['limit']);

                return new JsonMockResponse([
                    'code' => 0,
                    'data' => [
                        [
                            'id' => '123e4567-e89b-12d3-a456-426614174000',
                            '_vectors' => [0.1, 0.2, 0.3],
                            '_metadata' => '{}',
                            'distance' => 1.0,
                        ],
                        [
                            'id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
                            '_vectors' => [0.2, 0.1, 0.3],
                            '_metadata' => '{}',
                            'distance' => 0.9,
                        ],
                    ],
                ]);
            },
        ], 'http://127.0.0.1:19530');

        $store = new Store($httpClient, 'test', 'test_collection');

        $documents = iterator_to_array($store->findSimilar('123e4567-e89b-12d3-a456-426614174000', ['limit' => 1]), false);

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertCount(1, $documents);
        $this->assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $documents[0]->getId());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'default', 'test_collection');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'default', 'test_collection');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'default', 'test_collection');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 0,
                'data' => [
                    [
                        'count(*)' => 42,
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:19530');

        $store = new Store(
            $httpClient,
            'test',
            'test',
        );

        $this->assertSame(42, $store->count());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
