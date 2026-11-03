<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Pinecone\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class StoreTest extends TestCase
{
    private const INDEX_HOST = 'https://my-index-abc123.svc.aped-1234.pinecone.io';

    /**
     * @var list<array{method: string, url: string, headers: array<string, string>, raw_body: string, body: array<string, mixed>|null}>
     */
    private array $requests = [];

    public function testAddSingleDocument()
    {
        $uuid = Uuid::v4();

        $store = $this->createStore([new JsonMockResponse(['upsertedCount' => 1])]);
        $store->add(new VectorDocument($uuid, new Vector([0.1, 0.2, 0.3]), new Metadata(['title' => 'Test Document'])));

        $this->assertCount(1, $this->requests);
        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame(self::INDEX_HOST.'/vectors/upsert', $this->requests[0]['url']);
        $this->assertSame([
            'vectors' => [
                ['id' => (string) $uuid, 'values' => [0.1, 0.2, 0.3], 'metadata' => ['title' => 'Test Document']],
            ],
        ], $this->requests[0]['body']);
    }

    public function testAddMultipleDocumentsWithoutMetadata()
    {
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $store = $this->createStore([new JsonMockResponse(['upsertedCount' => 2])]);
        $store->add([
            new VectorDocument($uuid1, new Vector([0.1, 0.2, 0.3])),
            new VectorDocument($uuid2, new Vector([0.4, 0.5, 0.6]), new Metadata(['title' => 'Second Document'])),
        ]);

        $this->assertCount(1, $this->requests);
        $this->assertSame([
            'vectors' => [
                ['id' => (string) $uuid1, 'values' => [0.1, 0.2, 0.3]],
                ['id' => (string) $uuid2, 'values' => [0.4, 0.5, 0.6], 'metadata' => ['title' => 'Second Document']],
            ],
        ], $this->requests[0]['body']);
    }

    public function testAddWithNamespace()
    {
        $store = $this->createStore([new JsonMockResponse(['upsertedCount' => 1])], namespace: 'test-namespace');
        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])));

        $this->assertSame('test-namespace', $this->requests[0]['body']['namespace']);
    }

    public function testAddWithEmptyDocumentsSendsNothing()
    {
        $this->createStore([])->add([]);

        $this->assertSame([], $this->requests);
    }

    public function testRequestsCarryTheCredentialsAndTheApiVersion()
    {
        $store = $this->createStore([new JsonMockResponse(['upsertedCount' => 1])]);
        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1])));

        $this->assertSame('secret', $this->requests[0]['headers']['api-key']);
        $this->assertSame('2025-10', $this->requests[0]['headers']['x-pinecone-api-version']);
    }

    public function testRemoveSingleDocument()
    {
        $store = $this->createStore([new JsonMockResponse([])], namespace: 'test-namespace');
        $store->remove('vector-id');

        $this->assertCount(1, $this->requests);
        $this->assertSame(self::INDEX_HOST.'/vectors/delete', $this->requests[0]['url']);
        $this->assertSame(['ids' => ['vector-id'], 'namespace' => 'test-namespace'], $this->requests[0]['body']);
    }

    public function testRemoveSplitsIdsIntoChunksOfThousand()
    {
        $ids = [];
        for ($i = 0; $i < 1001; ++$i) {
            $ids[] = 'vector-id-'.$i;
        }

        $this->createStore([new JsonMockResponse([]), new JsonMockResponse([])])->remove($ids);

        $this->assertCount(2, $this->requests);
        $this->assertCount(1000, $this->requests[0]['body']['ids']);
        $this->assertSame(['vector-id-1000'], $this->requests[1]['body']['ids']);
    }

    public function testRemoveWithEmptyIdsSendsNothing()
    {
        $this->createStore([])->remove([]);

        $this->assertSame([], $this->requests);
    }

    public function testRemoveWithDeleteAllOption()
    {
        $this->createStore([new JsonMockResponse([])])->remove(['a', 'b'], ['deleteAll' => true]);

        $this->assertCount(1, $this->requests);
        $this->assertSame(['deleteAll' => true], $this->requests[0]['body']);
    }

    public function testClear()
    {
        $this->createStore([new JsonMockResponse([])], namespace: 'test-namespace')->clear();

        $this->assertSame(self::INDEX_HOST.'/vectors/delete', $this->requests[0]['url']);
        $this->assertSame(['deleteAll' => true, 'namespace' => 'test-namespace'], $this->requests[0]['body']);
    }

    public function testQueryReturnsDocuments()
    {
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $store = $this->createStore([new JsonMockResponse([
            'matches' => [
                [
                    'id' => (string) $uuid1,
                    'values' => [0.1, 0.2, 0.3],
                    'metadata' => ['title' => 'First Document'],
                    'score' => 0.95,
                ],
                [
                    'id' => (string) $uuid2,
                    'values' => [0.4, 0.5, 0.6],
                    'metadata' => ['title' => 'Second Document'],
                    'score' => 0.85,
                ],
            ],
        ])]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertSame(self::INDEX_HOST.'/query', $this->requests[0]['url']);
        $this->assertSame([
            'vector' => [0.1, 0.2, 0.3],
            'topK' => 3,
            'includeMetadata' => true,
            'includeValues' => false,
        ], $this->requests[0]['body']);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(VectorDocument::class, $results[0]);
        $this->assertEquals($uuid1, $results[0]->getId());
        $this->assertEquals($uuid2, $results[1]->getId());
        $this->assertSame(0.95, $results[0]->getScore());
        $this->assertSame(0.85, $results[1]->getScore());
        $this->assertSame('First Document', $results[0]->getMetadata()['title']);
        $this->assertSame([0.1, 0.2, 0.3], $results[0]->getVector()->getData());
    }

    public function testQueryWithNamespaceAndFilter()
    {
        $store = $this->createStore(
            [new JsonMockResponse(['matches' => []])],
            namespace: 'test-namespace',
            filter: ['category' => 'test'],
            topK: 5,
        );

        $this->assertCount(0, iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])))));
        $this->assertSame([
            'vector' => [0.1, 0.2, 0.3],
            'topK' => 5,
            'includeMetadata' => true,
            'includeValues' => false,
            'namespace' => 'test-namespace',
            'filter' => ['category' => 'test'],
        ], $this->requests[0]['body']);
    }

    public function testQueryWithCustomOptions()
    {
        $store = $this->createStore([new JsonMockResponse(['matches' => []])]);

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), [
            'namespace' => 'custom-namespace',
            'filter' => ['type' => 'document'],
            'topK' => 10,
            'includeValues' => true,
            'includeMetadata' => false,
        ]));

        $this->assertSame([
            'vector' => [0.1, 0.2, 0.3],
            'topK' => 10,
            'includeMetadata' => false,
            'includeValues' => true,
            'namespace' => 'custom-namespace',
            'filter' => ['type' => 'document'],
        ], $this->requests[0]['body']);
    }

    public function testQueryWithoutMetadataReturnsEmptyMetadata()
    {
        $store = $this->createStore([new JsonMockResponse([
            'matches' => [['id' => 'vector-id', 'score' => 0.95]],
        ])]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])), ['includeMetadata' => false]));

        $this->assertCount(1, $results);
        $this->assertCount(0, $results[0]->getMetadata());
        $this->assertInstanceOf(NullVector::class, $results[0]->getVector());
    }

    public function testQueryWithEmptyValuesReturnsNullVector()
    {
        $store = $this->createStore([new JsonMockResponse([
            'matches' => [['id' => 'vector-id', 'values' => [], 'metadata' => ['title' => 'Test Document'], 'score' => 0.95]],
        ])]);

        $results = iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3]))));

        $this->assertInstanceOf(NullVector::class, $results[0]->getVector());
    }

    public function testQueryRejectsUnsupportedQueries()
    {
        $this->expectException(UnsupportedQueryTypeException::class);

        iterator_to_array($this->createStore([])->query(new TextQuery('hello')));
    }

    public function testQueryFailsOnErrorResponse()
    {
        $store = $this->createStore([new JsonMockResponse(['message' => 'Invalid API Key'], ['http_code' => 401])]);

        $this->expectException(ClientExceptionInterface::class);

        iterator_to_array($store->query(new VectorQuery(new Vector([0.1]))));
    }

    public function testSetup()
    {
        $store = $this->createStore(
            [new JsonMockResponse([], ['http_code' => 201])],
            dimensions: 768,
            metric: 'dotproduct',
            cloud: 'gcp',
            region: 'europe-west4',
        );
        $store->setup();

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://api.pinecone.io/indexes', $this->requests[0]['url']);
        $this->assertSame([
            'name' => 'test-index',
            'dimension' => 768,
            'metric' => 'dotproduct',
            'spec' => ['serverless' => ['cloud' => 'gcp', 'region' => 'europe-west4']],
        ], $this->requests[0]['body']);
    }

    public function testSetupUsesDefaults()
    {
        $this->createStore([new JsonMockResponse([], ['http_code' => 201])])->setup();

        $this->assertSame([
            'name' => 'test-index',
            'dimension' => 1536,
            'metric' => 'cosine',
            'spec' => ['serverless' => ['cloud' => 'aws', 'region' => 'us-west-2']],
        ], $this->requests[0]['body']);
    }

    public function testSetupRejectsOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');

        $this->createStore([])->setup(['dimension' => 1536]);
    }

    public function testDrop()
    {
        $this->createStore([new MockResponse('', ['http_code' => 202])])->drop();

        $this->assertCount(1, $this->requests);
        $this->assertSame('DELETE', $this->requests[0]['method']);
        $this->assertSame('https://api.pinecone.io/indexes/test-index', $this->requests[0]['url']);
    }

    public function testIndexHostIsLookedUpOnceAndGetsTheSchemeOfTheControlPlane()
    {
        $store = $this->createStore([
            new JsonMockResponse(['host' => 'localhost:5081']),
            new JsonMockResponse(['upsertedCount' => 1]),
            new JsonMockResponse(['upsertedCount' => 1]),
        ], indexHost: false, controlPlaneUrl: 'http://127.0.0.1:5080/');

        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1])));
        $store->add(new VectorDocument(Uuid::v4(), new Vector([0.1])));

        $this->assertCount(3, $this->requests);
        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('http://127.0.0.1:5080/indexes/test-index', $this->requests[0]['url']);
        $this->assertSame('http://localhost:5081/vectors/upsert', $this->requests[1]['url']);
        $this->assertSame('http://localhost:5081/vectors/upsert', $this->requests[2]['url']);
    }

    public function testIndexHostLookupKeepsTheSchemeOfTheHost()
    {
        $store = $this->createStore([
            new JsonMockResponse(['host' => 'my-index-abc123.svc.pinecone.io']),
            new JsonMockResponse(['totalVectorCount' => 1]),
        ], indexHost: false);

        $store->count();

        $this->assertSame('https://my-index-abc123.svc.pinecone.io/describe_index_stats', $this->requests[1]['url']);
    }

    public function testIndexHostLookupFailsWithoutHost()
    {
        $store = $this->createStore([new JsonMockResponse(['name' => 'test-index'])], indexHost: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The host of the Pinecone index "test-index" is unknown.');

        $store->count();
    }

    public function testRemoveToleratesAnEmptyJsonStringAsResponse()
    {
        $this->createStore([new MockResponse('""')])->remove('vector-id');

        $this->assertCount(1, $this->requests);
    }

    public function testStoreSupportsVectorQuery()
    {
        $this->assertTrue($this->createStore([])->supports(VectorQuery::class));
    }

    public function testStoreDoesNotSupportTextQuery()
    {
        $this->assertFalse($this->createStore([])->supports(TextQuery::class));
    }

    public function testStoreDoesNotSupportHybridQuery()
    {
        $this->assertFalse($this->createStore([])->supports(HybridQuery::class));
    }

    public function testCountReturnsDocumentCount()
    {
        $store = $this->createStore([new JsonMockResponse(['totalVectorCount' => 42, 'namespaces' => []])]);

        $this->assertSame(42, $store->count());
        $this->assertSame(self::INDEX_HOST.'/describe_index_stats', $this->requests[0]['url']);
    }

    public function testCountSendsAnEmptyJsonObjectAsBody()
    {
        $this->createStore([new JsonMockResponse(['totalVectorCount' => 0])])->count();

        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('{}', $this->requests[0]['raw_body']);
    }

    public function testCountReturnsNamespaceDocumentCount()
    {
        $store = $this->createStore([new JsonMockResponse([
            'totalVectorCount' => 100,
            'namespaces' => ['my-namespace' => ['vectorCount' => 42]],
        ])], namespace: 'my-namespace');

        $this->assertSame(42, $store->count());
    }

    public function testCountReturnsZeroForUnknownNamespace()
    {
        $store = $this->createStore([new JsonMockResponse(['totalVectorCount' => 100, 'namespaces' => []])], namespace: 'my-namespace');

        $this->assertSame(0, $store->count());
    }

    /**
     * @param list<MockResponse>   $responses
     * @param array<string, mixed> $filter
     * @param string|false|null    $indexHost false resolves the host through the control plane
     */
    private function createStore(
        array $responses,
        ?string $namespace = null,
        array $filter = [],
        int $topK = 3,
        int $dimensions = 1536,
        ?string $metric = null,
        ?string $cloud = null,
        ?string $region = null,
        string|false|null $indexHost = null,
        string $controlPlaneUrl = 'https://api.pinecone.io',
    ): Store {
        $this->requests = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $headers = [];
            foreach ($options['headers'] as $header) {
                [$name, $value] = explode(': ', $header, 2);
                $headers[strtolower($name)] = $value;
            }

            $rawBody = $options['body'] ?? '';

            $this->requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'raw_body' => $rawBody,
                'body' => '' === $rawBody ? null : json_decode($rawBody, true),
            ];

            return array_shift($responses) ?? throw new \LogicException('Unexpected request.');
        });

        return new Store(
            $httpClient,
            'secret',
            'test-index',
            $namespace,
            $filter,
            $topK,
            $dimensions,
            $metric,
            $cloud,
            $region,
            false === $indexHost ? null : $indexHost ?? self::INDEX_HOST,
            $controlPlaneUrl,
        );
    }
}
