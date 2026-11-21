<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Weaviate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Weaviate\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupWithExtraOptions()
    {
        $store = new Store(new MockHttpClient(), 'test');

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
            new JsonMockResponse([
                'error' => [
                    'message' => 'foo',
                ],
            ], [
                'http_code' => 422,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 422 returned for "http://127.0.0.1:8080/v1/schema".');
        $this->expectExceptionCode(422);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'classes' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'class' => 'test',
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreSetupSkipsWhenCollectionExists()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'classes' => [
                    ['class' => 'test'],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSetupSkipsWhenCollectionExistsCaseInsensitive()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'classes' => [
                    ['class' => 'Test'],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => [
                    'message' => 'foo',
                ],
            ], [
                'http_code' => 422,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 422 returned for "http://127.0.0.1:8080/v1/schema/test".');
        $this->expectExceptionCode(422);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => [
                    'message' => 'foo',
                ],
            ], [
                'http_code' => 422,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 422 returned for "http://127.0.0.1:8080/v1/batch/objects".');
        $this->expectExceptionCode(422);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'objects' => [
                    [
                        'class' => 'test',
                        'id' => Uuid::v4()->toRfc4122(),
                        'vector' => [0.1, 0.2, 0.3],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => [
                    'message' => 'foo',
                ],
            ], [
                'http_code' => 422,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 422 returned for "http://127.0.0.1:8080/v1/graphql".');
        $this->expectExceptionCode(422);
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    'Get' => [
                        'test' => [
                            [
                                'uuid' => Uuid::v4()->toRfc4122(),
                                'vector' => [0.1, 0.2, 0.3],
                                '_metadata' => json_encode(['foo' => 'bar']),
                            ],
                            [
                                'uuid' => Uuid::v4()->toRfc4122(),
                                'vector' => [0.1, 0.2, 0.3],
                                '_metadata' => json_encode(['foo' => 'bar']),
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanClear()
    {
        $requestedMethod = null;
        $requestedUrl = null;
        $requestedBody = null;
        $httpClient = new MockHttpClient([
            static function (string $method, string $url, array $options) use (&$requestedMethod, &$requestedUrl, &$requestedBody): JsonMockResponse {
                $requestedMethod = $method;
                $requestedUrl = $url;
                $requestedBody = $options['body'];

                return new JsonMockResponse([
                    'results' => ['matches' => 2, 'successful' => 2, 'failed' => 0],
                ], ['http_code' => 200]);
            },
            new JsonMockResponse([
                'results' => ['matches' => 0, 'successful' => 0, 'failed' => 0],
            ], ['http_code' => 200]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $store->clear();

        $this->assertSame('DELETE', $requestedMethod);
        $this->assertSame('http://127.0.0.1:8080/v1/batch/objects', $requestedUrl);
        $this->assertSame([
            'match' => [
                'class' => 'test',
                'where' => [
                    'path' => ['id'],
                    'operator' => 'Like',
                    'valueText' => '*',
                ],
            ],
        ], json_decode((string) $requestedBody, true));
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreClearsBeyondTheBatchDeleteLimit()
    {
        // Weaviate only deletes up to QUERY_MAXIMUM_RESULTS objects per call, so clear() has to repeat
        // the batch delete until nothing matches anymore - otherwise documents would silently remain.
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => ['matches' => 11000, 'successful' => 10000, 'failed' => 0],
            ], ['http_code' => 200]),
            new JsonMockResponse([
                'results' => ['matches' => 1000, 'successful' => 1000, 'failed' => 0],
            ], ['http_code' => 200]),
            new JsonMockResponse([
                'results' => ['matches' => 0, 'successful' => 0, 'failed' => 0],
            ], ['http_code' => 200]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $store->clear();

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testStoreCannotClearWithFailedDeletes()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'results' => ['matches' => 2, 'successful' => 1, 'failed' => 1],
        ], ['http_code' => 200]), 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to delete 1 object(s) while clearing the "test" collection.');

        $store->clear();
    }

    public function testStoreCanFind()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('http://127.0.0.1:8080/v1/objects/test/123e4567-e89b-12d3-a456-426614174000', $url);
            $this->assertArrayNotHasKey('body', $options);

            return new JsonMockResponse([
                'class' => 'test',
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'properties' => [
                    'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                    'vector' => [0.1, 0.2, 0.3],
                    '_metadata' => json_encode(['foo' => 'bar']),
                ],
            ], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $document = $store->find('123e4567-e89b-12d3-a456-426614174000');

        $this->assertNotNull($document);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $document->getId());
        $this->assertSame([0.1, 0.2, 0.3], $document->getVector()->getData());
        $this->assertSame(['foo' => 'bar'], $document->getMetadata()->getArrayCopy());
        $this->assertNull($document->getScore());
    }

    public function testStoreReturnsNullWhenFindingAnUnknownDocument()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 404,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->assertNull($store->find('123e4567-e89b-12d3-a456-426614174000'));
    }

    public function testStoreCannotFindOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 601,
                'message' => 'id in path must be of type uuid: "foo"',
            ], [
                'http_code' => 422,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 422 returned for "http://127.0.0.1:8080/v1/objects/test/foo".');
        $this->expectExceptionCode(422);
        $store->find('foo');
    }

    public function testStoreCanFindSimilarDocuments()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'class' => 'test',
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'properties' => [
                    'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                    'vector' => [0.1, 0.2, 0.3],
                    '_metadata' => json_encode([]),
                ],
            ], [
                'http_code' => 200,
            ]),
            function (string $method, string $url, array $options): JsonMockResponse {
                $this->assertSame('http://127.0.0.1:8080/v1/graphql', $url);
                $this->assertStringContainsString('vector: [0.1, 0.2, 0.3]', $options['body']);
                $this->assertStringContainsString('limit: 2', $options['body']);

                return new JsonMockResponse([
                    'data' => [
                        'Get' => [
                            'test' => [
                                [
                                    'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                                    'vector' => [0.1, 0.2, 0.3],
                                    '_metadata' => json_encode([]),
                                ],
                                [
                                    'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
                                    'vector' => [0.2, 0.1, 0.3],
                                    '_metadata' => json_encode([]),
                                ],
                            ],
                        ],
                    ],
                ], [
                    'http_code' => 200,
                ]);
            },
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $documents = iterator_to_array($store->findSimilar('123e4567-e89b-12d3-a456-426614174000', ['limit' => 1]), false);

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertCount(1, $documents);
        $this->assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $documents[0]->getId());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8080');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8080');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:8080');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    'Aggregate' => [
                        'test' => [
                            [
                                'meta' => [
                                    'count' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');

        $this->assertSame(42, $store->count());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSendsIntegrationHeader()
    {
        $headers = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$headers) {
            $headers = $options['normalized_headers']['x-weaviate-client-integration'] ?? [];

            return new JsonMockResponse(['classes' => []]);
        }, 'http://127.0.0.1:8080');

        $store = new Store($httpClient, 'test');
        $store->drop();

        $this->assertCount(1, $headers);
        $this->assertMatchesRegularExpression('#^X-Weaviate-Client-Integration: symfony-ai/\S+$#', $headers[0]);
    }

    public function testQueryPassesTheLimit()
    {
        $response = new JsonMockResponse(['data' => ['Get' => ['test' => []]]]);
        $store = new Store(new MockHttpClient($response), 'test');

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 20]));

        $this->assertStringContainsString('limit: 20', json_decode($response->getRequestOptions()['body'], true)['query']);
    }
}
