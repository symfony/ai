<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cloudflare\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Cloudflare\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
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
    private const BASE_URI = 'https://api.cloudflare.com/client/v4/accounts/foo/';

    public function testStoreCannotSetupWithExtraOptions()
    {
        $store = new Store(new MockHttpClient(), 'random');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $store->setup([
            'foo' => 'bar',
        ]);
    }

    public function testStoreCannotSetupOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'config' => [
                        'dimensions' => 1536,
                        'metric' => 'cosine',
                    ],
                    'name' => 'random',
                ],
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->setup();

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'messages' => [
                    'code' => 1000,
                    'message' => 'foo',
                ],
                'result' => [],
                'success' => true,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->drop();

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotClearWithExtraOptions()
    {
        $store = new Store(new MockHttpClient(), 'random');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only the "batch_size" option is supported.');
        $this->expectExceptionCode(0);
        $store->clear([
            'foo' => 'bar',
        ]);
    }

    public function testStoreCannotClearWithInvalidBatchSize()
    {
        $store = new Store(new MockHttpClient(), 'random');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "batch_size" option must be a positive integer.');
        $store->clear(['batch_size' => 0]);
    }

    public function testStoreCanClearWithCustomBatchSize()
    {
        $mockHttpClient = new MockHttpClient([
            function (string $method, string $url): JsonMockResponse {
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/list?count=250', $url);

                return new JsonMockResponse([
                    'result' => ['vectors' => [], 'isTruncated' => false],
                    'success' => true,
                ]);
            },
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->clear(['batch_size' => 250]);

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCanClear()
    {
        $mockHttpClient = new MockHttpClient([
            function (string $method, string $url): JsonMockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/list?count=1000', $url);

                return new JsonMockResponse([
                    'result' => [
                        'vectors' => [['id' => 'foo'], ['id' => 'bar']],
                        'isTruncated' => false,
                    ],
                    'success' => true,
                ]);
            },
            function (string $method, string $url, array $options): JsonMockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/delete_by_ids', $url);

                $this->assertIsString($options['body']);
                $this->assertSame(['ids' => ['foo', 'bar']], json_decode($options['body'], true));

                return new JsonMockResponse(['result' => [], 'success' => true]);
            },
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->clear();

        $this->assertSame(2, $mockHttpClient->getRequestsCount());
    }

    public function testStoreClearsFollowingThePaginationCursor()
    {
        // the listing is a snapshot, so already deleted vectors keep showing up until the cursor is exhausted
        $mockHttpClient = new MockHttpClient([
            function (string $method, string $url): JsonMockResponse {
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/list?count=1000', $url);

                return new JsonMockResponse([
                    'result' => [
                        'vectors' => [['id' => 'foo']],
                        'isTruncated' => true,
                        'nextCursor' => 'next-page',
                    ],
                    'success' => true,
                ]);
            },
            new JsonMockResponse(['result' => [], 'success' => true]),
            function (string $method, string $url): JsonMockResponse {
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/list?count=1000&cursor=next-page', $url);

                return new JsonMockResponse([
                    'result' => [
                        'vectors' => [['id' => 'bar']],
                        'isTruncated' => false,
                    ],
                    'success' => true,
                ]);
            },
            new JsonMockResponse(['result' => [], 'success' => true]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->clear();

        $this->assertSame(4, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCanClearEmptyIndex()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'vectors' => [],
                    'isTruncated' => false,
                ],
                'success' => true,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->clear();

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotClearOnMalformedListResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse(['success' => true]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Cloudflare list response is malformed.');
        $store->clear();
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/upsert".');
        $this->expectExceptionCode(400);
        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);
    }

    public function testStoreCanAdd()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'mutationId' => '1',
                ],
                'success' => true,
            ], [
                'http_code' => 200,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/query".');
        $this->expectExceptionCode(400);
        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));
    }

    public function testStoreCanQuery()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'matches' => [
                        [
                            'score' => 1.0,
                            'id' => Uuid::v4()->toRfc4122(),
                            'values' => [0.1, 0.2, 0.3],
                            'metadata' => [],
                        ],
                        [
                            'score' => 1.0,
                            'id' => Uuid::v4()->toRfc4122(),
                            'values' => [0.1, 0.2, 0.3],
                            'metadata' => [],
                        ],
                    ],
                ],
            ], [
                'http_code' => 200,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotRemoveOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cloudflare.com/client/v4/accounts/foo/vectorize/v2/indexes/random/delete_by_ids".');
        $this->expectExceptionCode(400);
        $store->remove(['id1', 'id2']);
    }

    public function testStoreCanRemoveWithSingleId()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'mutationId' => '1',
                ],
                'success' => true,
            ], [
                'http_code' => 200,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->remove('id1');

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCanRemoveWithMultipleIds()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    'mutationId' => '1',
                ],
                'success' => true,
            ], [
                'http_code' => 200,
            ]),
        ], self::BASE_URI);

        $store = new Store($mockHttpClient, 'random');

        $store->remove(['id1', 'id2', 'id3']);

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_index');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_index');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'test_index');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
