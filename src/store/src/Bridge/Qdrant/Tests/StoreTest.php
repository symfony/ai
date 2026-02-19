<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Qdrant\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Qdrant\Store;
use Symfony\AI\Store\Document\Metadata;
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
            new JsonMockResponse([
                'status' => 'ok',
                'result' => [
                    'exists' => false,
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:6333/collections/test".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCanSetupOnExistingCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'status' => 'ok',
                'result' => [
                    'exists' => true,
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'status' => 'ok',
                'result' => true,
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:6333/collections/test".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDropOnExistingCollection()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'status' => 'ok',
                'result' => [
                    'exists' => true,
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'status' => 'ok',
                'result' => [
                    'exists' => false,
                ],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'status' => 'ok',
                'result' => true,
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:6333/collections/test/points?wait=true".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCanAdd()
    {
        $document = new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]));

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use ($document): JsonMockResponse {
            self::assertArrayHasKey('wait', $options['query']);
            self::assertSame('true', $options['query']['wait']);

            return new JsonMockResponse([
                'time' => 0.002,
                'status' => 'ok',
                'result' => [
                    'points' => [
                        [
                            'id' => (string) $document->getId(),
                            'payload' => (array) $document->getMetadata(),
                            'vector' => $document->getVector()->getData(),
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $store->add($document);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanAddAsynchronously()
    {
        $document = new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]));

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): JsonMockResponse {
            self::assertArrayHasKey('wait', $options['query']);
            self::assertSame('false', $options['query']['wait']);

            return new JsonMockResponse([
                'time' => 0.002,
                'status' => 'ok',
                'result' => [
                    'status' => 'acknowledged',
                    'operation_id' => 1000000,
                ],
            ], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test', async: true);

        $store->add($document);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:6333',
            'test',
            'test',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:6333/collections/test/points/query".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'points' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            'vector' => [0.1, 0.2, 0.3],
                            'payload' => [],
                        ],
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            'vector' => [0.2, 0.1, 0.3],
                            'payload' => [],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertCount(2, $results);
    }

    public function testStoreCanQueryWithFilters()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'points' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            'vector' => [0.1, 0.2, 0.3],
                            'payload' => ['foo' => 'bar'],
                        ],
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            'vector' => [0.2, 0.1, 0.3],
                            'payload' => ['foo' => ['bar', 'baz']],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), [
            'filter' => [
                'must' => [
                    ['key' => 'foo', 'match' => ['value' => 'bar']],
                ],
            ],
        ]));

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('foo', $result->getMetadata());
            $this->assertTrue(
                'bar' === $result->getMetadata()['foo'] || (\is_array($result->getMetadata()['foo']) && \in_array('bar', $result->getMetadata()['foo'], true)),
                "Value should be 'bar' or an array containing 'bar'"
            );
        }
    }

    public function testStoreCannotRemoveOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:6333/collections/test/points/delete?wait=true".');
        $this->expectExceptionCode(400);
        $store->remove('test-id');
    }

    public function testStoreCanRemoveSingleId()
    {
        $id = Uuid::v4()->toRfc4122();

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use ($id): JsonMockResponse {
            self::assertSame('POST', $method);
            self::assertArrayHasKey('wait', $options['query']);
            self::assertSame('true', $options['query']['wait']);
            self::assertIsString($options['body']);
            self::assertSame(['points' => [$id]], json_decode($options['body'], true));

            return new JsonMockResponse([
                'time' => 0.002,
                'status' => 'ok',
                'result' => [
                    'operation_id' => 0,
                    'status' => 'completed',
                ],
            ], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $store->remove($id);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveMultipleIds()
    {
        $ids = [Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122()];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use ($ids): JsonMockResponse {
            self::assertSame('POST', $method);
            self::assertArrayHasKey('wait', $options['query']);
            self::assertSame('true', $options['query']['wait']);
            self::assertIsString($options['body']);
            self::assertSame(['points' => $ids], json_decode($options['body'], true));

            return new JsonMockResponse([
                'time' => 0.003,
                'status' => 'ok',
                'result' => [
                    'operation_id' => 0,
                    'status' => 'completed',
                ],
            ], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $store->remove($ids);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCanRemoveAsynchronously()
    {
        $id = Uuid::v4()->toRfc4122();

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use ($id): JsonMockResponse {
            self::assertSame('POST', $method);
            self::assertArrayHasKey('wait', $options['query']);
            self::assertSame('false', $options['query']['wait']);
            self::assertIsString($options['body']);
            self::assertSame(['points' => [$id]], json_decode($options['body'], true));

            return new JsonMockResponse([
                'time' => 0.002,
                'status' => 'ok',
                'result' => [
                    'operation_id' => 1000001,
                    'status' => 'acknowledged',
                ],
            ], [
                'http_code' => 200,
            ]);
        }, 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test', async: true);

        $store->remove($id);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:6333', 'test-api-key', 'test_collection');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:6333', 'test-api-key', 'test_collection');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQueryWhenDisabled()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:6333', 'test-api-key', 'test_collection');
        $this->assertFalse($store->supports(HybridQuery::class));
    }

    public function testStoreSupportsHybridQueryWhenEnabled()
    {
        $store = new Store(new MockHttpClient(), 'http://localhost:6333', 'test-api-key', 'test_collection', hybridEnabled: true);
        $this->assertTrue($store->supports(HybridQuery::class));
    }

    public function testHybridSetupCreatesNamedVectors()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'status' => 'ok',
                'result' => ['exists' => false],
            ], ['http_code' => 200]),
            new JsonMockResponse([
                'status' => 'ok',
                'result' => true,
            ], ['http_code' => 200]),
        ], 'http://127.0.0.1:6333');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:6333',
            'test',
            'test',
            embeddingsDimension: 768,
            hybridEnabled: true,
        );

        $store->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testHybridSetupPayloadStructure()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            if ('PUT' === $method) {
                $capturedBody = json_decode($options['body'], true);
            }

            return new JsonMockResponse([
                'status' => 'ok',
                'result' => 'GET' === $method ? ['exists' => false] : true,
            ], ['http_code' => 200]);
        }, 'http://127.0.0.1:6333');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:6333',
            'test',
            'test',
            embeddingsDimension: 768,
            hybridEnabled: true,
            denseVectorName: 'dense',
            sparseVectorName: 'bm25',
        );

        $store->setup();

        $this->assertArrayHasKey('vectors', $capturedBody);
        $this->assertArrayHasKey('dense', $capturedBody['vectors']);
        $this->assertSame(768, $capturedBody['vectors']['dense']['size']);
        $this->assertSame('Cosine', $capturedBody['vectors']['dense']['distance']);
        $this->assertArrayHasKey('sparse_vectors', $capturedBody);
        $this->assertArrayHasKey('bm25', $capturedBody['sparse_vectors']);
        $this->assertSame('idf', $capturedBody['sparse_vectors']['bm25']['modifier']);
    }

    public function testHybridAddIncludesSparseVector()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'status' => 'ok',
                'result' => ['status' => 'completed'],
            ], ['http_code' => 200]);
        }, 'http://127.0.0.1:6333');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:6333',
            'test',
            'test',
            hybridEnabled: true,
        );

        $metadata = new Metadata(['_text' => 'green ogre swamp']);
        $document = new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]), $metadata);

        $store->add($document);

        $point = $capturedBody['points'][0];
        $this->assertArrayHasKey('dense', $point['vector']);
        $this->assertArrayHasKey('bm25', $point['vector']);
        $this->assertSame([0.1, 0.2, 0.3], $point['vector']['dense']);
        $this->assertArrayHasKey('indices', $point['vector']['bm25']);
        $this->assertArrayHasKey('values', $point['vector']['bm25']);
    }

    public function testHybridQueryUsesFormulaWithDefaultRatio()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'result' => [
                    'points' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            'payload' => ['_text' => 'test'],
                            'score' => 0.9,
                        ],
                    ],
                ],
            ], ['http_code' => 200]);
        }, 'http://127.0.0.1:6333');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:6333',
            'test',
            'test',
            hybridEnabled: true,
        );

        $results = iterator_to_array($store->query(
            new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'green ogre'),
            ['limit' => 5],
        ));

        $this->assertArrayHasKey('prefetch', $capturedBody);
        $this->assertCount(2, $capturedBody['prefetch']);
        $this->assertSame('bm25', $capturedBody['prefetch'][0]['using']);
        $this->assertSame('dense', $capturedBody['prefetch'][1]['using']);
        $this->assertSame(15, $capturedBody['prefetch'][0]['limit']);

        $this->assertArrayHasKey('formula', $capturedBody['query']);
        $expectedFormula = [
            'sum' => [
                ['mult' => [0.5, '$score[0]']],
                ['mult' => [0.5, '$score[1]']],
            ],
        ];
        $this->assertSame($expectedFormula, $capturedBody['query']['formula']);
        $this->assertArrayNotHasKey('defaults', $capturedBody['query']);

        $this->assertSame(5, $capturedBody['limit']);
        $this->assertTrue($capturedBody['with_payload']);
        $this->assertCount(1, $results);
    }

    public function testHybridQueryUsesFormulaWithCustomRatio()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'result' => [
                    'points' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            'payload' => [],
                            'score' => 0.85,
                        ],
                    ],
                ],
            ], ['http_code' => 200]);
        }, 'http://127.0.0.1:6333');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:6333',
            'test',
            'test',
            hybridEnabled: true,
        );

        iterator_to_array($store->query(
            new HybridQuery(new Vector([0.1, 0.2, 0.3]), 'space exploration', 0.8),
            ['limit' => 10],
        ));

        $formula = $capturedBody['query']['formula'];
        $this->assertEqualsWithDelta(0.2, $formula['sum'][0]['mult'][0], 0.001);
        $this->assertSame('$score[0]', $formula['sum'][0]['mult'][1]);
        $this->assertEqualsWithDelta(0.8, $formula['sum'][1]['mult'][0], 0.001);
        $this->assertSame('$score[1]', $formula['sum'][1]['mult'][1]);
    }

    public function testHybridQueryWithVectorQueryFallsToDenseOnly()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'result' => [
                    'points' => [
                        [
                            'id' => Uuid::v4()->toRfc4122(),
                            'payload' => [],
                            'score' => 0.8,
                        ],
                    ],
                ],
            ], ['http_code' => 200]);
        }, 'http://127.0.0.1:6333');

        $store = new Store(
            $httpClient,
            'http://127.0.0.1:6333',
            'test',
            'test',
            hybridEnabled: true,
        );

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 5]));

        $this->assertArrayNotHasKey('prefetch', $capturedBody);
        $this->assertSame([0.1, 0.2, 0.3], $capturedBody['query']);
        $this->assertSame('dense', $capturedBody['using']);
    }

    public function testTokenize()
    {
        $store = new Store(
            new MockHttpClient([], 'http://127.0.0.1:6333'),
            'http://127.0.0.1:6333',
            'test',
            'test',
            hybridEnabled: true,
        );

        $reflection = new \ReflectionMethod($store, 'tokenize');

        $result = $reflection->invoke($store, 'Hello World hello');

        $this->assertArrayHasKey('indices', $result);
        $this->assertArrayHasKey('values', $result);
        $this->assertCount(2, $result['indices']);
        $this->assertCount(2, $result['values']);

        $helloIndex = array_search(abs(crc32('hello')), $result['indices']);
        $worldIndex = array_search(abs(crc32('world')), $result['indices']);

        $this->assertNotFalse($helloIndex);
        $this->assertNotFalse($worldIndex);
        $this->assertSame(2.0, $result['values'][$helloIndex]);
        $this->assertSame(1.0, $result['values'][$worldIndex]);
    }

    public function testNonHybridSetupUsesUnnamedVector()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            if ('PUT' === $method) {
                $capturedBody = json_decode($options['body'], true);
            }

            return new JsonMockResponse([
                'status' => 'ok',
                'result' => 'GET' === $method ? ['exists' => false] : true,
            ], ['http_code' => 200]);
        }, 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $store->setup();

        $this->assertArrayHasKey('vectors', $capturedBody);
        $this->assertArrayHasKey('size', $capturedBody['vectors']);
        $this->assertArrayNotHasKey('sparse_vectors', $capturedBody);
    }

    public function testNonHybridAddUsesPlainVector()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody): JsonMockResponse {
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'status' => 'ok',
                'result' => ['status' => 'completed'],
            ], ['http_code' => 200]);
        }, 'http://127.0.0.1:6333');

        $store = new Store($httpClient, 'http://127.0.0.1:6333', 'test', 'test');

        $document = new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]));
        $store->add($document);

        $point = $capturedBody['points'][0];
        $this->assertSame([0.1, 0.2, 0.3], $point['vector']);
    }
}
