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
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupWithExtraOptions()
    {
        $store = new Store(
            new MockHttpClient(),
            'foo',
            'bar',
            'random'
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

        $store->setup();

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

        $store->drop();

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

        $store->add([new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3]))]);

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreCannotQueryOnInvalidResponse()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

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
        ]);

        $store = new Store(
            $mockHttpClient,
            'foo',
            'bar',
            'random',
        );

        $store->remove(['id1', 'id2', 'id3']);

        $this->assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'account-id', 'index-name', 'api-key');
        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'account-id', 'index-name', 'api-key');
        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'account-id', 'index-name', 'api-key');
        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
