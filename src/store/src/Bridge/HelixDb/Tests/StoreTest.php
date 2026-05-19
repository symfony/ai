<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\HelixDb\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\HelixDb\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class StoreTest extends TestCase
{
    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['documents' => []], ['http_code' => 200]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotSetupWhenQueriesAreNotDeployed()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], ['http_code' => 404]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The HelixDB store is not reachable or the canonical HelixQL queries are not deployed.');
        $store->setup();
    }

    public function testStoreThrowsExceptionOnSetupWithOptions()
    {
        $store = new Store(new MockHttpClient(), 'http://127.0.0.1:6969');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $store->setup(['foo' => 'bar']);
    }

    public function testStoreCanAdd()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], ['http_code' => 200]),
            new JsonMockResponse([], ['http_code' => 200]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])),
            new VectorDocument(Uuid::v4(), new Vector([0.4, 0.5, 0.6])),
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCanQuery()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'documents' => [
                    [
                        'doc_id' => 'doc-1',
                        'vector' => [0.1, 0.2, 0.3],
                        'metadata' => '{"foo":"bar"}',
                        'score' => 0.1,
                    ],
                    [
                        'doc_id' => 'doc-2',
                        'vector' => [0.4, 0.5, 0.6],
                        'metadata' => '{"foo":"baz"}',
                        'score' => 0.2,
                    ],
                ],
            ], ['http_code' => 200]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(2, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertSame('doc-1', $results[0]->getId());
        $this->assertSame(0.1, $results[0]->getScore());
        $this->assertSame(['foo' => 'bar'], $results[0]->getMetadata()->getArrayCopy());
        $this->assertSame([0.1, 0.2, 0.3], $results[0]->getVector()->getData());
    }

    public function testStoreCanQueryWithLimitOption()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'documents' => [
                    ['doc_id' => 'doc-1', 'vector' => [0.1, 0.2, 0.3], 'metadata' => '{}', 'score' => 0.1],
                ],
            ], ['http_code' => 200]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['limit' => 10]));

        $this->assertCount(1, $results);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreReturnsNoResultWhenResponseHasNoDocuments()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], ['http_code' => 200]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertCount(0, $results);
    }

    public function testStoreThrowsExceptionOnUnsupportedQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://127.0.0.1:6969');

        $this->expectException(UnsupportedQueryTypeException::class);
        $store->query(new TextQuery('foo'));
    }

    public function testStoreCanRemove()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], ['http_code' => 200]),
            new JsonMockResponse([], ['http_code' => 200]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $store->remove(['doc-1', 'doc-2']);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreDoesNothingWhenRemovingEmptyList()
    {
        $httpClient = new MockHttpClient([], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $store->remove([]);

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], ['http_code' => 200]),
        ], 'http://127.0.0.1:6969');

        $store = new Store($httpClient, 'http://127.0.0.1:6969', 3);
        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreThrowsExceptionOnDropWithOptions()
    {
        $store = new Store(new MockHttpClient(), 'http://127.0.0.1:6969');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $store->drop(['foo' => 'bar']);
    }

    public function testStoreSupportsVectorQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://127.0.0.1:6969');

        $this->assertTrue($store->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://127.0.0.1:6969');

        $this->assertFalse($store->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $store = new Store(new MockHttpClient(), 'http://127.0.0.1:6969');

        $this->assertFalse($store->supports(HybridQuery::class));
    }
}
