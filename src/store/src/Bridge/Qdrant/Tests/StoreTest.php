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
        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])));
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
                            'id' => (string) $document->id,
                            'payload' => (array) $document->metadata,
                            'vector' => $document->vector->getData(),
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
        iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));
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

        $results = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3])));

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

        $results = iterator_to_array($store->query(new Vector([0.1, 0.2, 0.3]), [
            'filter' => [
                'must' => [
                    ['key' => 'foo', 'match' => ['value' => 'bar']],
                ],
            ],
        ]));

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('foo', $result->metadata);
            $this->assertTrue(
                'bar' === $result->metadata['foo'] || (\is_array($result->metadata['foo']) && \in_array('bar', $result->metadata['foo'], true)),
                "Value should be 'bar' or an array containing 'bar'"
            );
        }
    }
}
